<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\ArticleGenerationService;
use App\Services\ContentEngineService;
use App\Services\ContentPublishService;
use App\Services\TrendingTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentIdeaController extends Controller
{
    public function __construct(
        private ArticleGenerationService $articleGen,
        private ContentEngineService $engine,
        private TrendingTopicService $trending,
        private ContentPublishService $publishService
    ) {}

    /**
     * List ideas with optional filters.
     * Auto-syncs status for any 'researching' or 'generating_images' ideas.
     */
    public function index(Request $request): JsonResponse
    {
        // Eager-load post.published_at so the Completed tab can show the
        // blog-live timestamp instead of source_data.pub_date. Select only
        // id + published_at on the related Post to keep payload small.
        $query = ContentIdea::query()->with(['post:id,published_at']);

        if ($request->has('pillar')) {
            $query->byPillar($request->query('pillar'));
        }
        if ($request->has('status')) {
            $query->byStatus($request->query('status'));
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->query('priority'));
        }
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->query('search') . '%');
        }

        // Admin page ships without client-side pagination so the trending-score
        // sort covers the entire dataset. Cap at 500 to prevent runaway queries
        // if the table ever explodes in size.
        $perPage = min((int) $request->query('per_page', 500), 500);
        $ideas = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Stuck-process detection moved off the hot path — the per-idea
        // SSH `kill -0` probe added 1-3s × N latency on production VPS and
        // routinely tripped the 30s frontend timeout. The reaper crons
        // (linkedin:reap-stuck, ProcessPendingImages, etc.) own that
        // responsibility now. Per-idea progress polling still calls
        // syncIdeaStatus() in getProgress() where one SSH hop is fine.

        // Trending-priority sort: HOT topics with many trusted publishers and
        // fresh pub_dates float to row 1 so the user works the highest-signal
        // headlines first. Manual (non-trending-import) ideas fall back to
        // created_at order. Uses fields populated by TrendingTopicService
        // during import and stored in source_data JSON.
        $items = $ideas->items();
        usort($items, fn ($a, $b) => $this->computeTrendingScore($b) <=> $this->computeTrendingScore($a));
        $ideas->setCollection(collect($items));

        // Surface blog-live timestamp to the admin UI. Frontend prefers this
        // over source_data.pub_date on Completed rows so the Published column
        // shows "posted 30m ago" instead of "news article published 3d ago".
        // Strip heavy detail-only fields from the LIST payload. These three
        // alone were ~96% of a 5.4MB response (generated_article 86%,
        // progress_log 5.5%, research_data 4.5%) — the full article HTML,
        // streaming progress log, and raw research JSON are never rendered in
        // the list table and routinely tripped the 15s axios timeout on the
        // Content Engine page (symptom: tabs show 0 / "No ideas found").
        // Detail/preview/finalize views fetch the full row via the show()
        // endpoint (GET /ideas/{id}); the progress modal refills progress_log
        // from getProgress() polling. Cuts the payload to ~210KB.
        $data = collect($ideas->items())->map(function (ContentIdea $idea) {
            $arr = $idea->toArray();
            unset($arr['generated_article'], $arr['progress_log'], $arr['research_data']);
            $arr['result_post_published_at'] = $idea->post?->published_at?->toIso8601String();
            return $arr;
        })->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $ideas->currentPage(),
                'last_page' => $ideas->lastPage(),
                'per_page' => $ideas->perPage(),
                'total' => $ideas->total(),
            ],
        ]);
    }

    /**
     * Create a new content idea.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'source' => 'sometimes|in:manual,google_trends,youtube,tiktok,google_news,instagram',
            'pillar' => 'sometimes|in:vibe_coding,ai_automation,ai_agents,ai_video_image,general',
            'priority' => 'sometimes|in:low,medium,high',
            'description' => 'nullable|string',
            'niche' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'languages' => 'nullable|array',
            'auto_mode' => 'sometimes|boolean',
            'scheduled_at' => 'nullable|date',
        ]);

        $validated['status'] = 'draft';
        $idea = ContentIdea::create($validated);

        return response()->json([
            'success' => true,
            'data' => $idea,
            'message' => 'Content idea created successfully.',
        ], 201);
    }

    /**
     * Show a single content idea by ID.
     */
    public function show($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $idea,
        ]);
    }

    /**
     * Update an existing content idea.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        if (in_array($idea->status, ['researching', 'generating_images'])) {
            return response()->json(['success' => false, 'message' => 'Cannot update while processing.'], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'pillar' => 'sometimes|in:vibe_coding,ai_automation,ai_agents,ai_video_image,general',
            'priority' => 'sometimes|in:low,medium,high',
            'niche' => 'sometimes|string|max:100',
            'tags' => 'nullable|array',
            'languages' => 'nullable|array',
            'description' => 'nullable|string',
            'auto_mode' => 'sometimes|boolean',
            'scheduled_at' => 'nullable|date',
        ]);

        $idea->update($validated);

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Content idea updated successfully.',
        ]);
    }

    /**
     * Delete a content idea permanently.
     */
    public function destroy($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }
        if (in_array($idea->status, ['researching', 'generating_images'])) {
            return response()->json(['success' => false, 'message' => 'Cannot delete while processing.'], 422);
        }

        $idea->delete();
        return response()->json(['success' => true, 'message' => 'Content idea deleted successfully.']);
    }

    /**
     * Archive a content idea.
     */
    public function archive($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $idea->transitionTo(ContentIdeaStatus::Archived, 'admin_archive');
        return response()->json(['success' => true, 'data' => $idea->fresh(), 'message' => 'Content idea archived.']);
    }

    /**
     * Restore an archived idea back to draft.
     */
    public function restore($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $idea->transitionTo(ContentIdeaStatus::Draft, 'admin_restore');
        return response()->json(['success' => true, 'data' => $idea->fresh(), 'message' => 'Content idea restored to draft.']);
    }

    /**
     * Revert idea back to draft (from article_ready or images_ready).
     */
    public function revertToDraft($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        if (!in_array($idea->status, ['article_ready', 'images_ready'])) {
            return response()->json(['success' => false, 'message' => 'Can only revert from article_ready or images_ready.'], 422);
        }

        $idea->transitionTo(ContentIdeaStatus::Draft, 'admin_revert', [
            'research_data' => null,
            'generated_article' => null,
            'generated_images' => null,
            'image_instructions' => null,
            'image_references' => null,
        ]);

        return response()->json(['success' => true, 'data' => $idea->fresh(), 'message' => 'Reverted to draft.']);
    }

    /**
     * Resume pipeline from the last incomplete sub-step.
     * Unlike revertToDraft, this preserves partial progress:
     *   - prep_data present + no article body → resumes at article-write
     *   - article body present + no scores → resumes at article-score
     *   - article_ready → dispatches image generation
     *   - completed + no post → publishes
     *
     * Works for both stuck 'researching' ideas and terminal 'failed' ideas.
     */
    public function resumePipeline($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        if (!in_array($idea->status, ['researching', 'failed', 'generating_images', 'article_ready', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Resume requires status researching, failed, generating_images, article_ready, or completed. Current: ' . $idea->status,
            ], 422);
        }

        try {
            $orchestrator = app(\App\Services\AutoPipelineOrchestrator::class);
            $resumed = $orchestrator->resumeIdea($idea);

            return response()->json([
                'success' => true,
                'data' => $resumed->fresh(),
                'message' => 'Pipeline resumed from ' . $resumed->current_step,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resume failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ========================================================================
    // TRENDING TOPICS
    // ========================================================================

    /**
     * Pull trending topics from all sources.
     */
    public function pullTrending(Request $request): JsonResponse
    {
        // Lifts PHP-FPM's default max_execution_time=30s ceiling. Aggregating
        // 4 external feeds (Google News × 5 RSS, Google Trends × 2 regions,
        // TikTok scrape, 3 YouTube Piped instances mostly dead) routinely
        // burns ~15s of curl time alone — and FPM kills the worker before
        // the response ships, surfacing in the browser as
        // "Network error - no response received". nginx fastcgi_read_timeout
        // is already 300s; this brings PHP in line.
        set_time_limit(0);

        $source = $request->query('source');
        $trends = $this->trending->getAllTrends($source);

        if (!$source || $source === 'instagram') {
            try {
                $instagramTrends = $this->engine->getInstagramTrending();
                $formatted = array_map(fn($item) => [
                    'title' => $item['caption'] ?? $item['title'] ?? 'Untitled',
                    'source' => 'instagram',
                    'score' => $item['score'] ?? 60,
                ], $instagramTrends);
                $trends = array_merge($trends, $formatted);
            } catch (\Exception $e) {
                // Instagram trending failed, continue with other sources
            }
        }

        return response()->json(['success' => true, 'data' => $trends]);
    }

    /**
     * Extract an int field from a mixed array, or null when missing/invalid.
     * Used by importTrending() to carry virality scores safely.
     */
    private function resolveInt(array $data, string $key): ?int
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }
        $value = $data[$key];
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }

    /**
     * Pull trending topics AND score them for virality before returning.
     * Wraps TrendingTopicService::getScoredTopics() — sorted desc by composite_score,
     * capped at TopicScoringService::MAX_BATCH_SIZE (20) topics.
     */
    public function scoreTrendingBatch(Request $request): JsonResponse
    {
        // Same rationale as pullTrending(), but with stricter need: this
        // path layers 3 sequential Sonnet sync calls (60 topics ÷ batch of 20)
        // on top of the same external aggregation, taking 100-150s end to end.
        // Without lifting max_execution_time, PHP-FPM kills the worker and
        // the browser sees "Network error - no response received".
        set_time_limit(0);

        // Request::input() reads from both body and query string by default,
        // so this handles the composable's POST-body call and any future
        // ?source=... query-string call with a single lookup.
        $source = $request->input('source') ?: null;

        $scored = $this->trending->getScoredTopics($source);

        return response()->json([
            'success' => true,
            'data' => $scored,
        ]);
    }

    /**
     * Import selected trending topics as content ideas.
     *
     * Virality gate: rejects topics whose headline score (composite_score
     * with virality_score fallback) falls below
     * config('content.trending.virality_threshold', 70). Mirrors the gate
     * applied by the daily PullTrendingDaily cron so manual UI imports
     * can't bypass the editorial bar. Skipped topics are returned in the
     * response payload so the operator sees what was filtered + why.
     */
    public function importTrending(Request $request): JsonResponse
    {
        $request->validate([
            'topics' => 'required|array|min:1',
            'topics.*.title' => 'required|string|max:500',
            'topics.*.source' => 'nullable|string',
        ]);

        // Read raw input (not $request->validated()) so enrichment fields
        // (heat, publisher, publisher_tier, pub_date, publisher_count, etc.)
        // survive into source_data. The validated() array only contains keys
        // listed in rules above, which silently drops everything else.
        $topics = (array) $request->input('topics', []);

        $threshold = (int) config('content.trending.virality_threshold', 70);

        $imported = [];
        $skipped = [];
        foreach ($topics as $topic) {
            if (empty($topic['title'])) continue;

            // Carry scored signals (Phase A) forward onto the ContentIdea
            // so downstream tiering/prioritization can read them without
            // re-running the scorer. composite_score wins as the headline
            // virality_score if present; else raw virality_score; else null.
            $composite = $this->resolveInt($topic, 'composite_score');
            $virality = $this->resolveInt($topic, 'virality_score');
            $momentum = $this->resolveInt($topic, 'momentum_score');
            $triggers = $topic['triggers'] ?? null;

            $headlineScore = $composite ?? $virality;

            // Hard editorial gate. Unscored topics (headlineScore === null)
            // are rejected too — the upstream Pull Trending modal always
            // surfaces scored topics now, so an unscored row reaching this
            // endpoint indicates a frontend bug or direct-API misuse.
            if ($headlineScore === null || $headlineScore < $threshold) {
                $skipped[] = [
                    'title' => $topic['title'],
                    'virality_score' => $headlineScore,
                    'reason' => $headlineScore === null
                        ? 'unscored'
                        : "below_threshold ({$headlineScore}<{$threshold})",
                ];
                continue;
            }

            $payload = [
                'title' => $topic['title'],
                'source' => $topic['source'] ?? 'manual',
                'status' => 'draft',
                'source_data' => $topic,
                'virality_score' => $headlineScore,
            ];

            if ($composite !== null || $virality !== null || $momentum !== null || is_array($triggers)) {
                $payload['virality_breakdown'] = [
                    'momentum' => $momentum,
                    'virality' => $virality,
                    'composite' => $composite,
                    'triggers' => is_array($triggers) ? $triggers : null,
                ];
            }

            $imported[] = ContentIdea::create($payload);
        }

        $message = count($imported) . ' topic(s) imported';
        if (!empty($skipped)) {
            $message .= ', ' . count($skipped) . " skipped (virality < {$threshold})";
        }
        $message .= '.';

        return response()->json([
            'success' => true,
            'data' => $imported,
            'skipped' => $skipped,
            'threshold' => $threshold,
            'message' => $message,
        ], 201);
    }

    /**
     * Phase C opt-in: dispatch the Sonnet /article-score skill on demand.
     * Only allowed when the idea is in 'article_ready' — the default Phase C
     * skip path leaves ideas at that status with just a mechanical snapshot.
     *
     * Stuck-idea recovery: if the /article-score plugin hangs and never calls
     * back, the idea is stuck at status='researching', current_step='deep_scoring'.
     * To recover manually:
     *   UPDATE content_ideas
     *     SET status='article_ready', progress_percentage=100,
     *         current_step='mechanical_snapshot', process_pid=NULL
     *     WHERE id=<stuck_id>;
     * Mechanical snapshot remains intact, so the scorecard still renders and
     * the user can re-click Run Deep Quality Analysis. Future work: add an
     * admin "Reset to article_ready" button in ArticlePreview.vue that wraps
     * this recovery via a dedicated endpoint.
     */
    public function runDeepScore($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        if ($idea->status !== 'article_ready') {
            return response()->json([
                'success' => false,
                'message' => "Cannot run deep score: idea must be in article_ready status (current: {$idea->status}).",
            ], 409);
        }

        // Content guard — the skip path in continue-pipeline flips status to
        // article_ready even when generated_article is empty (so downstream
        // image dispatch isn't blocked). But /article-score needs real content
        // to score, so reject that edge case here with a clear message.
        $content = trim((string) data_get($idea->generated_article, 'content', ''));
        if ($content === '') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot run deep score: article content is empty. Regenerate or save the article first.',
            ], 409);
        }

        $result = $this->articleGen->triggerScore($idea->id);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Deep score dispatch failed.',
            ], 500);
        }

        $idea->transitionTo(ContentIdeaStatus::Researching, 'admin_deep_score', [
            'progress_percentage' => 85,
            'current_step' => 'deep_scoring',
            'process_pid' => $result['pid'] ?? null,
            'progress_log' => array_merge(
                $idea->progress_log ?? [],
                [[
                    'timestamp' => now()->toISOString(),
                    'step' => 'deep_scoring_started',
                    'percentage' => 85,
                    'message' => 'User-requested deep quality analysis (Sonnet /article-score)',
                ]]
            ),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['pid' => $result['pid'] ?? null],
        ]);
    }

    // ========================================================================
    // GATE 1: ARTICLE GENERATION (Research + Write)
    // ========================================================================

    /**
     * Start article generation via Claude Code CLI + article-content-writer plugin.
     * Triggers SSH to VPS to run the article-gen skill.
     */
    public function startResearch($id, Request $request): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $validated = $request->validate([
            'languages' => 'required|array|min:1',
            'languages.*' => 'in:en,id',
            'instructions' => 'nullable|string',
            'research_tier' => 'nullable|string|in:auto,quick,deep',
        ]);

        $researchTier = $validated['research_tier'] ?? 'auto';

        $idea->transitionTo(ContentIdeaStatus::Researching, 'admin_start_research', [
            'output_types' => ['blog_article'],
            'languages' => $validated['languages'],
            'instructions' => $validated['instructions'] ?? null,
            'research_tier_override' => $researchTier,
            'progress_percentage' => 0,
            'current_step' => 'initializing',
            'progress_log' => [[
                'timestamp' => now()->toISOString(),
                'step' => 'initializing',
                'percentage' => 0,
                'message' => 'Article generation triggered',
            ]],
        ]);

        // Phase B.7: gate the split-skill flow (viral-first v3) behind a feature flag.
        // When ON we dispatch /article-research via triggerResearch with a resolved tier.
        // When OFF we fall through to the legacy split (triggerPrep) or single (triggerGeneration) path.
        $skillSplitEnabled = (bool) config('services.article_generation.skill_split_enabled', false);
        $useLegacySplitPipeline = !$skillSplitEnabled && !empty(config('services.article_generation.refs_prep'));

        $config = [
            'topic' => $idea->title,
            'languages' => $validated['languages'],
            'instructions' => $validated['instructions'] ?? '',
        ];

        if ($skillSplitEnabled) {
            $resolvedTier = $this->articleGen->resolveResearchTier($idea->fresh());
            $result = $this->articleGen->triggerResearch($idea->id, $config, $resolvedTier);
        } elseif ($useLegacySplitPipeline) {
            $result = $this->articleGen->triggerPrep($idea->id, $config);
        } else {
            $result = $this->articleGen->triggerGeneration($idea->id, $config);
        }

        if ($result['success']) {
            $idea->update([
                'process_pid' => $result['pid'],
                'workflows' => [[
                    'type' => 'blog_article',
                    'driver' => 'claude_cli',
                    'pipeline' => $skillSplitEnabled ? 'research_split' : ($useLegacySplitPipeline ? 'split' : 'single'),
                    'pid' => $result['pid'],
                    'status' => 'running',
                    'created_at' => now()->toISOString(),
                ]],
            ]);
        } else {
            Log::warning('[ContentIdea] Article generation trigger failed: ' . ($result['error'] ?? 'Unknown'));
            $idea->update([
                'current_step' => 'failed',
                'progress_log' => array_merge($idea->progress_log ?? [], [[
                    'timestamp' => now()->toISOString(),
                    'step' => 'failed',
                    'percentage' => 0,
                    'message' => 'Failed to start: ' . ($result['error'] ?? 'Unknown error'),
                ]]),
            ]);
        }

        if ($result['success']) {
            $successMessage = match (true) {
                $skillSplitEnabled => 'Viral-first research pipeline started (research → strategy → write → score).',
                $useLegacySplitPipeline => 'Split pipeline started (prep → write → score).',
                default => 'Article generation started via CLI.',
            };
        } else {
            $successMessage = 'Generation trigger failed, but idea is in researching state.';
        }

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => $successMessage,
        ]);
    }

    /**
     * Regenerate article — reset to researching and re-trigger CLI generation.
     * Used when the user wants to re-run the improved plugin version.
     */
    public function regenerateArticle($id, Request $request): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        // 'completed' is allowed so admin can re-run the pipeline on a
        // published idea. The live Post stays at /blog/{slug} until
        // re-publish (ContentPublishService uses Post::updateOrCreate keyed on
        // id to rewrite in place). FSM transition below handles the
        // completed → researching edge.
        $allowedStatuses = ['article_ready', 'images_ready', 'completed'];
        // Also allow regeneration from failed researching state
        if ($idea->status === 'researching' && $idea->current_step === 'failed') {
            $allowedStatuses[] = 'researching';
        }
        if (!in_array($idea->status, $allowedStatuses)) {
            return response()->json(['success' => false, 'message' => 'Can only regenerate from article_ready, images_ready, completed, or failed state.'], 422);
        }

        $languages = $idea->languages ?? ['id'];
        $instructions = $request->input('instructions', $idea->instructions);
        $previousStatus = $idea->status;

        // Trigger generation FIRST — only clear data after success
        $result = $this->articleGen->triggerGeneration($idea->id, [
            'topic' => $idea->title,
            'languages' => $languages,
            'instructions' => $instructions ?? '',
        ]);

        if ($result['success']) {
            // Only wipe old article after trigger succeeds. Route status
            // change through transitionTo so the state-log captures the
            // regenerate origin + allowed-from set (article_ready,
            // images_ready, or failed researching).
            $idea->transitionTo(ContentIdeaStatus::Researching, 'admin_regenerate', [
                'generated_article' => null,
                'generated_images' => null,
                'image_instructions' => null,
                'image_references' => null,
                'progress_percentage' => 0,
                'current_step' => 'initializing',
                'instructions' => $instructions,
                'process_pid' => $result['pid'],
                'progress_log' => [[
                    'timestamp' => now()->toISOString(),
                    'step' => 'initializing',
                    'percentage' => 0,
                    'message' => 'Article regeneration triggered (improved plugin)',
                ]],
                'workflows' => [[
                    'type' => 'blog_article',
                    'driver' => 'claude_cli',
                    'pid' => $result['pid'],
                    'status' => 'running',
                    'created_at' => now()->toISOString(),
                    'regenerated' => true,
                ]],
            ]);

            return response()->json([
                'success' => true,
                'data' => $idea->fresh(),
                'message' => 'Article regeneration started.',
            ]);
        }

        // Trigger failed — preserve old article, stay in current status
        Log::warning('[ContentIdea] Regeneration trigger failed: ' . ($result['error'] ?? 'Unknown'));

        return response()->json([
            'success' => false,
            'message' => 'Failed to start regeneration: ' . ($result['error'] ?? 'Unknown error'),
        ], 500);
    }

    /**
     * Get idea with generated article for preview.
     */
    public function getResearch($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        // Auto-sync if still researching
        if ($idea->status === 'researching') {
            $this->syncIdeaStatus($idea);
            $idea->refresh();
        }

        return response()->json(['success' => true, 'data' => $idea]);
    }

    /**
     * GATE 1: Approve article text. Moves to image generation stage.
     */
    public function approveArticle($id, Request $request): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        if ($idea->status !== 'article_ready') {
            return response()->json(['success' => false, 'message' => 'Article must be ready before approval.'], 422);
        }

        // Optionally update the article title/content if user edited in preview
        if ($request->has('title')) {
            $idea->title = $request->input('title');
        }
        if ($request->has('generated_article')) {
            $idea->generated_article = $request->input('generated_article');
        }

        $idea->save();

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Article text approved. Ready for image generation.',
        ]);
    }

    // ========================================================================
    // GATE 2: IMAGE GENERATION
    // ========================================================================

    /**
     * Start image generation with optional instructions and reference images.
     */
    public function startImageGeneration($id, Request $request): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        if ($idea->status !== 'article_ready') {
            return response()->json(['success' => false, 'message' => 'Article must be approved before image generation.'], 422);
        }

        $request->validate([
            'image_instructions' => 'nullable|string|max:2000',
            'image_references' => 'nullable|array',
            'image_references.*' => 'file|image|max:10240',
        ]);

        // Save image instructions
        $idea->image_instructions = $request->input('image_instructions');

        // Handle reference image uploads
        $referenceUrls = [];
        if ($request->hasFile('image_references')) {
            foreach ($request->file('image_references') as $file) {
                $path = $file->store('content-engine/references', 'public');
                $referenceUrls[] = url('/storage/' . $path);
            }
        }
        $idea->image_references = $referenceUrls;
        // Flush instructions/references with save(), then FSM-transition the status.
        $idea->save();
        $idea->transitionTo(ContentIdeaStatus::GeneratingImages, 'admin_start_image_generation');

        // Gate 2 split-phase flow: when flag is on and no prompts exist yet,
        // invoke /article-images skill to author cinematic prompts before GeminiGen.
        $article = $idea->generated_article ?? [];
        if (
            config('services.article_generation.use_images_phase')
            && empty(data_get($article, 'image_prompts'))
        ) {
            $idempotencyKey = Str::uuid()->toString();
            $result = $this->articleGen->triggerImages($idea->id, $idempotencyKey);
            $idea->update([
                'process_pid' => $result['pid'] ?? null,
                'current_step' => 'authoring_image_prompts',
                'progress_percentage' => 5,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'mode' => 'authoring_prompts',
                    'idempotency_key' => $idempotencyKey,
                    'pid' => $result['pid'] ?? null,
                ],
                'message' => 'Authoring image prompts via article-images skill.',
            ]);
        }

        // Legacy flow: trigger image generation via Content Engine
        try {
            $result = $this->engine->createWorkflow('image_generation', [
                'topic' => $idea->title,
                'article_title' => $article['title'] ?? $idea->title,
                'article_content' => $article['content'] ?? '',
                'image_instructions' => $idea->image_instructions ?? '',
                'reference_images' => $referenceUrls,
                'idea_id' => $idea->id,
            ]);

            $workflows = $idea->workflows ?? [];
            $workflows[] = [
                'type' => 'image_generation',
                'workflow_id' => $result['id'] ?? null,
                'status' => 'pending',
                'created_at' => now()->toISOString(),
            ];
            $idea->workflows = $workflows;
            $idea->save();
        } catch (\Exception $e) {
            Log::warning('[ContentIdea] Image gen workflow failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Image generation started.',
        ]);
    }

    /**
     * Save draft — persist edits to generated_article without changing status.
     */
    public function saveDraft($id, Request $request): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        if ($request->has('generated_article')) {
            $incoming = $request->input('generated_article');
            $existing = $idea->generated_article ?? [];

            // Merge image_prompts per-segment: preserve backend-managed fields
            // (variations[], status, job_uuid, generated_url, error) when the
            // incoming payload omits them. Frontend strips these deliberately
            // (see ImageGeneration.vue persistDraft) so the prior replace-all
            // save was wiping webhook results on every keystroke.
            if (isset($incoming['image_prompts']) && is_array($incoming['image_prompts'])) {
                $existingPrompts = $existing['image_prompts'] ?? [];
                $preserveFields = ['variations', 'status', 'job_uuid', 'generated_url', 'error'];
                foreach ($incoming['image_prompts'] as $i => $newPrompt) {
                    $existingPrompt = $existingPrompts[$i] ?? [];
                    foreach ($preserveFields as $field) {
                        if (!array_key_exists($field, $newPrompt) && array_key_exists($field, $existingPrompt)) {
                            $newPrompt[$field] = $existingPrompt[$field];
                        }
                    }
                    $incoming['image_prompts'][$i] = $newPrompt;
                }
            }

            $idea->generated_article = $incoming;
        }
        $idea->save();

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Draft saved.',
        ]);
    }

    /**
     * Generate a single image for a specific segment via GeminiGen.
     */
    public function generateSegmentImage($id, Request $request): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $request->validate([
            'segment_index' => 'required|integer|min:0',
            'prompt' => 'required|string|max:2000',
            'style' => 'required|string|max:100',
            'model' => 'nullable|string|max:50',
            'aspect_ratio' => 'nullable|string|max:10',
            'face_refs' => 'nullable|array',
            'face_refs.*' => 'string|max:2000',
            'style_refs' => 'nullable|array',
            'style_refs.*' => 'string|max:2000',
            'brand_refs' => 'nullable|array',
            'brand_refs.*' => 'string|max:2000',
            'additional_notes' => 'nullable|string|max:1000',
            // Optional: replace a specific existing variation slot (any status)
            // instead of using the retry-in-place / append behavior. Used by
            // the "Replace this variation" UI button on done thumbnails.
            'replace_variation_index' => 'nullable|integer|min:0|max:2',
        ]);

        $segmentIndex = $request->input('segment_index');
        $replaceIndex = $request->input('replace_variation_index');
        $model = $request->input('model', config('content.default_image_model', 'nano-banana-pro'));
        $aspectRatio = $request->input('aspect_ratio', '16:9');
        $style = $request->input('style');
        $prompt = $request->input('prompt');
        // Strip browser-only blob: URLs — GeminiGen can't fetch them (400 FILE_DOWNLOAD_FAILED)
        $isUsableRef = fn ($u) => is_string($u) && $u !== '' && !str_starts_with($u, 'blob:');
        $faceRefs = array_values(array_filter((array) ($request->input('face_refs') ?? []), $isUsableRef));
        $styleRefs = array_values(array_filter((array) ($request->input('style_refs') ?? []), $isUsableRef));
        // brand_refs can be {filename, url} objects or flat URL strings
        $rawBrandRefs = (array) ($request->input('brand_refs') ?? []);
        $brandRefUrls = [];
        $brandRefObjects = [];
        foreach ($rawBrandRefs as $ref) {
            if (is_array($ref) && !empty($ref['url']) && $isUsableRef($ref['url'])) {
                $brandRefUrls[] = $ref['url'];
                $brandRefObjects[] = $ref;
            } elseif (is_string($ref) && $isUsableRef($ref)) {
                $brandRefUrls[] = $ref;
                $brandRefObjects[] = ['filename' => '', 'url' => $ref];
            }
        }
        // ConvertEmptyStringsToNull middleware turns '' into null — coerce back for type-safety
        $additionalNotes = $request->input('additional_notes') ?? '';

        // Update segment reference data
        $article = $idea->generated_article ?? [];
        $imagePrompts = $article['image_prompts'] ?? [];
        if (isset($imagePrompts[$segmentIndex])) {
            $imagePrompts[$segmentIndex]['face_refs'] = $faceRefs;
            $imagePrompts[$segmentIndex]['style_refs'] = $styleRefs;
            $imagePrompts[$segmentIndex]['brand_refs'] = $brandRefObjects;
            $imagePrompts[$segmentIndex]['additional_notes'] = $additionalNotes;

            // Initialize variations array if not present
            if (!isset($imagePrompts[$segmentIndex]['variations'])) {
                // Migrate legacy flat generated_url into variations[0]
                $legacyUrl = $imagePrompts[$segmentIndex]['generated_url'] ?? null;
                $legacyUuid = $imagePrompts[$segmentIndex]['job_uuid'] ?? null;
                if ($legacyUrl) {
                    $imagePrompts[$segmentIndex]['variations'] = [[
                        'url' => $legacyUrl,
                        'job_uuid' => $legacyUuid,
                        'status' => 'done',
                    ]];
                    $imagePrompts[$segmentIndex]['selected_variation'] = 0;
                } else {
                    $imagePrompts[$segmentIndex]['variations'] = [];
                    $imagePrompts[$segmentIndex]['selected_variation'] = 0;
                }
            }

            $variations = $imagePrompts[$segmentIndex]['variations'];

            // Explicit replace path — operator clicked "Replace" on a specific
            // thumbnail. Overrides the retry-in-place heuristic and always
            // targets the requested slot, regardless of its current status
            // (done/failed/generating). Validates the slot actually exists
            // so a malicious or stale request can't append instead.
            $reuseIndex = null;
            if ($replaceIndex !== null) {
                if (!isset($variations[$replaceIndex])) {
                    return response()->json([
                        'success' => false,
                        'message' => "Variation slot {$replaceIndex} does not exist.",
                    ], 422);
                }
                $reuseIndex = $replaceIndex;
            }

            // Retry-in-place: reuse a failed or orphaned slot before appending. An
            // orphan is status=generating with no job_uuid — left over when the
            // queue call or save crashed before the UUID was persisted.
            if ($reuseIndex === null) {
                foreach ($variations as $vi => $v) {
                    $vStatus = $v['status'] ?? '';
                    $isFailed = $vStatus === 'failed';
                    $isOrphan = $vStatus === 'generating' && empty($v['job_uuid']);
                    if ($isFailed || $isOrphan) {
                        $reuseIndex = $vi;
                        break;
                    }
                }
            }

            if ($reuseIndex !== null) {
                $variationIndex = $reuseIndex;
                $priorSlot = $variations[$reuseIndex];
                $variations[$reuseIndex] = ['url' => null, 'job_uuid' => null, 'status' => 'generating'];
            } else {
                if (count($variations) >= 3) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Maximum 3 variations per segment.',
                    ], 422);
                }
                $variationIndex = count($variations);
                $priorSlot = null;
                $variations[] = ['url' => null, 'job_uuid' => null, 'status' => 'generating'];
            }
            $imagePrompts[$segmentIndex]['variations'] = $variations;
            $imagePrompts[$segmentIndex]['status'] = 'generating';
            $article['image_prompts'] = $imagePrompts;
            $idea->generated_article = $article;
        }

        // Persist generated_article changes, then FSM-transition status.
        $idea->save();
        if ($idea->status === 'article_ready') {
            $idea->transitionTo(ContentIdeaStatus::GeneratingImages, 'admin_generate_segment_image');
        }

        // Apply branded cover enhancement: inject post title overlay + creator
        // face auto-reference for covers that depict humans. Non-cover prompts
        // pass through untouched. Force model override for covers.
        $segmentMeta = $article['image_prompts'][$segmentIndex] ?? [];
        $forEnhance = array_merge($segmentMeta, [
            'prompt_text' => $prompt,
            'face_refs' => array_merge($faceRefs, $brandRefUrls),
            'model' => $model,
        ]);
        $enhanced = app(\App\Services\CoverBrandingEnhancer::class)->enhance($forEnhance, $idea);
        $finalPrompt = $enhanced['prompt_text'] ?? $prompt;
        $finalModel = $enhanced['model'] ?? $model;
        $finalFaceRefs = $enhanced['face_refs'] ?? array_merge($faceRefs, $brandRefUrls);

        // Watermark logo URL the enhancer pushed into file_urls. Merge into
        // styleRefs so it reaches GeminiGen as a multipart file reference —
        // the more specific watermark instruction in $finalPrompt dominates.
        // Without this, the logo URL never ships and the model invents a
        // letterform watermark from scratch (rendered the brand slug "AD"
        // instead of the actual uploaded logo PNG).
        $enhancerFileUrls = array_values(array_filter(
            $enhanced['file_urls'] ?? [],
            fn ($u) => is_string($u) && $u !== ''
        ));
        $finalStyleRefs = array_merge($styleRefs, $enhancerFileUrls);

        // Call GeminiGen via ImageGenerationService
        $imageService = app(\App\Services\ImageGenerationService::class);
        $uuid = $imageService->queue(
            postId: null,
            prompt: $finalPrompt,
            type: $segmentIndex === 0 ? 'hero' : 'inline',
            insertAfterHeading: null,
            model: $finalModel,
            aspectRatio: $aspectRatio,
            style: $style,
            faceRefs: $finalFaceRefs,
            styleRefs: $finalStyleRefs,
            additionalNotes: $additionalNotes
        );

        if (!$uuid) {
            // Restore the slot we touched. array_pop would drop the LAST slot which
            // may be unrelated — so restore/remove by actual variationIndex.
            $imagePrompts = $article['image_prompts'];
            if ($reuseIndex !== null && $priorSlot !== null) {
                $imagePrompts[$segmentIndex]['variations'][$variationIndex] = $priorSlot;
            } else {
                array_splice($imagePrompts[$segmentIndex]['variations'], $variationIndex, 1);
            }
            $vs = $imagePrompts[$segmentIndex]['variations'];
            $anyGenerating = collect($vs)->contains(fn ($v) => ($v['status'] ?? '') === 'generating');
            $anyDone = collect($vs)->contains(fn ($v) => ($v['status'] ?? '') === 'done' && !empty($v['url']));
            $imagePrompts[$segmentIndex]['status'] = $anyGenerating ? 'generating' : ($anyDone ? 'done' : 'pending');
            $article['image_prompts'] = $imagePrompts;
            $idea->generated_article = $article;
            $idea->save();

            return response()->json([
                'success' => false,
                'message' => 'Image generation failed to start.',
            ], 500);
        }

        // Store UUID in the new variation slot
        $imagePrompts = $article['image_prompts'];
        $imagePrompts[$segmentIndex]['variations'][$variationIndex]['job_uuid'] = $uuid;
        // Also set flat job_uuid for backward compat with polling
        $imagePrompts[$segmentIndex]['job_uuid'] = $uuid;
        $article['image_prompts'] = $imagePrompts;
        $idea->generated_article = $article;
        $idea->save();

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $uuid,
                'segment_index' => $segmentIndex,
                'variation_index' => $variationIndex,
            ],
            'message' => 'Image generation started.',
        ]);
    }

    /**
     * Retry a single failed segment without re-running the plugin's NER
     * pipeline. Reuses cached entity_refs/face_refs/brand_refs. Fast path
     * for the common case of transient GeminiGen failures.
     *
     * Returns 409 when the segment is at the retry cap, 404 when idea or
     * segment index doesn't exist, 500 when the GeminiGen queue call fails.
     */
    public function retrySegment($id, int $i): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $prompts = $idea->generated_article['image_prompts'] ?? [];
        if (!isset($prompts[$i])) {
            return response()->json(['success' => false, 'message' => 'Segment index out of range.'], 404);
        }

        $segment = $prompts[$i];
        $retryCount = (int) ($segment['retry_count'] ?? 0);

        // Safety-rewrite escape hatch. If the segment is at-cap because of
        // GeminiGen safety refusals (PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD,
        // etc.) and we haven't already rewritten the prompt, do it now and
        // reset the retry counter — the sanitized prompt is effectively a
        // new prompt and deserves a fresh budget. This is the operator's
        // "unstuck me" button for stuck-named-entity covers.
        $imgSvc = app(\App\Services\ImageGenerationService::class);
        $fh = $segment['failure_history'] ?? [];
        $lastFailure = !empty($fh) ? $fh[count($fh) - 1] : null;
        $lastWasSafety = $lastFailure
            && (($lastFailure['safety_detected'] ?? false) || $imgSvc->isSafetyError($lastFailure['reason'] ?? null));
        $alreadyRewritten = !empty($segment['visual_direction_pre_safety']);

        if ($retryCount >= $imgSvc->maxSegmentAttempts()) {
            if (!$lastWasSafety) {
                return response()->json([
                    'success' => false,
                    'message' => 'Segment retry cap reached.',
                    'retry_count' => $retryCount,
                ], 409);
            }

            // At-cap + safety: apply rewrite (if not already) and reset.
            if (!$alreadyRewritten && config('services.article_generation.use_safety_rewrite', true)) {
                $rewritten = $imgSvc->applySafetyRewrite($idea, $i, $lastFailure['reason'] ?? 'safety refusal');
                if (!$rewritten) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Safety rewrite failed — check ArticleGeneration logs.',
                    ], 500);
                }
                $idea->refresh();
            }

            // Reset cap so the sanitized prompt gets a fresh 3-attempt budget.
            $imgSvc->resetSegmentRetryBudget($idea, $i);
            $idea->refresh();
            $retryCount = 0;
        }

        $uuid = $imgSvc->retrySegment($idea, $i);

        if (!$uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Retry failed to dispatch. Check image generation logs.',
            ], 500);
        }

        // retry_count reflects failures observed; the retry dispatch itself
        // does NOT mutate it (see ImageGenerationService::retrySegment).
        // Return the current value so the UI can label "Attempt N/MAX" where
        // N = retry_count (next failure will bump it).
        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $uuid,
                'segment_index' => $i,
                'retry_count' => $retryCount,
            ],
            'message' => 'Retry dispatched.',
        ]);
    }

    /**
     * Manually skip a segment so the idea can advance even with a failure.
     * Sets status='skipped' + terminal_at=now(). Appends a user_skip entry
     * to failure_history[]. Refuses to skip the cover (index 0) without an
     * explicit `force=true` body param — cover skip has blast radius.
     */
    public function skipSegment($id, int $i, Request $request): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $article = $idea->generated_article ?? [];
        $prompts = $article['image_prompts'] ?? [];

        if (!isset($prompts[$i])) {
            return response()->json(['success' => false, 'message' => 'Segment index out of range.'], 404);
        }

        $currentStatus = $prompts[$i]['status'] ?? '';
        if ($currentStatus === 'done') {
            return response()->json([
                'success' => false,
                'message' => 'Segment already done — skip refused.',
            ], 409);
        }

        // Block skip while a GeminiGen job is still in flight — otherwise
        // the webhook can race and flip the variation to done on an
        // already-skipped segment, leaving the UI in a corrupted mixed
        // state. Admin must wait for the in-flight attempt to resolve
        // (fail) before skipping.
        if ($currentStatus === 'generating') {
            return response()->json([
                'success' => false,
                'message' => 'Segment is still generating — wait for the attempt to complete before skipping.',
                'code' => 'SEGMENT_STILL_GENERATING',
            ], 409);
        }

        $force = $request->boolean('force');
        if ($i === 0 && !$force) {
            return response()->json([
                'success' => false,
                'message' => 'Cover segment skip requires force=true — operator confirmation.',
                'code' => 'COVER_SKIP_REQUIRES_FORCE',
            ], 409);
        }

        $segment = $prompts[$i];
        $segment['status'] = 'skipped';
        $segment['terminal_at'] = now()->toIso8601String();
        $segment['failure_history'] = $segment['failure_history'] ?? [];
        $segment['failure_history'][] = [
            'attempt' => (int) ($segment['retry_count'] ?? 0),
            'uuid' => null,
            'reason' => 'user_skip',
            'timestamp' => now()->toIso8601String(),
        ];

        $prompts[$i] = $segment;
        $article['image_prompts'] = $prompts;
        $idea->update(['generated_article' => $article]);

        return response()->json([
            'success' => true,
            'data' => [
                'segment_index' => $i,
                'status' => 'skipped',
                'terminal_at' => $segment['terminal_at'],
            ],
            'message' => 'Segment skipped.',
        ]);
    }

    /**
     * GATE 2: Approve images and publish article to blog.
     *
     * Thin wrapper around ContentPublishService::publish — the same service
     * is called by AutoPipelineOrchestrator in auto-mode. Keeps HTTP concerns
     * here; all publish logic lives in the service.
     */
    public function approveAndPublish($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        // Defense-in-depth (GEO image-completion gate): never compile/publish
        // while any image segment is unresolved (failed / needs_operator). The
        // FSM gates already block auto-advance to images_ready, but a force-set
        // status, a race, or a legacy row could still reach here.
        $prompts = $idea->generated_article['image_prompts'] ?? [];
        if (!empty($prompts) && !\App\Services\ImageGenerationService::segmentsResolvedForAdvance($prompts)) {
            $unresolved = collect($prompts)
                ->filter(fn ($p) => !in_array($p['status'] ?? '', ['done', 'skipped'], true))
                ->count();

            return response()->json([
                'success' => false,
                'message' => "Cannot publish: {$unresolved} image segment(s) have not finished generating. Retry or skip them first.",
            ], 422);
        }

        try {
            $result = $this->publishService->publish($idea);
        } catch (\DomainException $e) {
            $status = str_contains($e->getMessage(), 'No category') ? 409 : 422;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }

        $post = $result['post'];

        // Event-driven LinkedIn draft ingest (June 10, 2026) now lives in
        // ContentPublishService::publish — the shared chokepoint for both this
        // manual path and the autonomous AutoPipelineOrchestrator path. No
        // dispatch here (would be a redundant second queue; the command is
        // idempotent anyway).

        // Blog→Medium cross-post (2026-06-13, Phase K). Non-fatal: a Medium
        // hand-off must never break the publish response. No-ops unless
        // postiz_enabled + postiz_medium_enabled + a mapped medium channel.
        try {
            app(\App\Services\PostizPublishDispatcher::class)->dispatchBlogToMedium($post);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[approveAndPublish] blog→Medium dispatch failed (non-fatal)', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }

        $translationPending = $result['translation_pending'];

        return response()->json([
            'success' => true,
            'data' => [
                'idea' => $idea->fresh(),
                'published_post_id' => $post->id,
                'post_slug' => $post->slug,
                'translation_pending' => $translationPending,
            ],
            'message' => $translationPending
                ? 'Published — English translation in progress.'
                : 'Published.',
        ]);
    }


    // ========================================================================
    // FINALIZE: Translation Phase (post-publish)
    // ========================================================================

    /**
     * Automation: return primary-language post data for translation skill.
     */
    public function getPostForTranslation($id): JsonResponse
    {
        $post = Post::with('translations')->find($id);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $primary = $post->translations->first();
        if (!$primary) {
            return response()->json([
                'success' => false,
                'message' => 'Post has no primary translation to translate from.',
            ], 409);
        }

        // Extract image alt map from content HTML
        $imageAltMap = [];
        if ($primary->content) {
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $primary->content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $srcUrl = $match[1];
                $altMatch = [];
                preg_match('/alt=["\']([^"\']*)["\']/i', $match[0], $altMatch);
                $imageAltMap[$srcUrl] = $altMatch[1] ?? '';
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'primary_language' => $primary->language,
                'title' => $primary->title,
                'slug' => $primary->slug,
                'content' => $primary->content,
                'excerpt' => $primary->excerpt,
                'meta_title' => $primary->meta_title,
                'meta_description' => $primary->meta_description,
                'og_title' => $primary->og_title,
                'og_description' => $primary->og_description,
                'ai_summary' => $primary->ai_summary,
                'image_alt_map' => $imageAltMap,
            ],
        ]);
    }

    /**
     * Automation: save translated post_translations row.
     */
    public function saveTranslation($id, Request $request): JsonResponse
    {
        $request->validate([
            'target_locale' => 'required|string|in:en,id',
            'translation.title' => 'required|string|max:255',
            'translation.slug' => 'required|string|max:255',
            'translation.content' => 'required|string',
            'translation.meta_title' => 'nullable|string|max:70',
            'translation.meta_description' => 'nullable|string|max:170',
            'translation.og_title' => 'nullable|string|max:100',
            'translation.og_description' => 'nullable|string|max:200',
            'translation.excerpt' => 'nullable|string|max:500',
            'translation.ai_summary' => 'nullable|string',
            'translation.image_alt_map' => 'nullable|array',
        ]);

        $post = Post::find($id);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $t = $request->input('translation');
        $locale = $request->input('target_locale');

        // Sanitize JSON-escape leakage on every string field. The translate
        // plugin output sometimes carries `\/` escapes (e.g. `<\/p>`) that
        // DOMParser won't recognize as closing tags, cascading the whole
        // article into the prior tag's styling. Apply defensively before
        // img-alt rewrite so the rewritten content is also clean.
        $t = \App\Support\HtmlSlashSanitizer::apply($t);
        $translatedContent = $t['content'];

        // Rewrite <img alt="..."> in translated content if image_alt_map provided
        if (!empty($t['image_alt_map']) && is_array($t['image_alt_map'])) {
            foreach ($t['image_alt_map'] as $srcUrl => $newAlt) {
                $pattern = '/(<img[^>]+src=["\']' . preg_quote($srcUrl, '/') . '["\'][^>]*?)alt=["\'][^"\']*["\']/i';
                $replacement = '$1alt="' . addslashes($newAlt) . '"';
                $result = preg_replace($pattern, $replacement, $translatedContent);
                if ($result !== null) {
                    $translatedContent = $result;
                }
            }
        }

        $pt = PostTranslation::updateOrCreate(
            ['post_id' => $post->id, 'language' => $locale],
            [
                'title' => $t['title'],
                'slug' => $t['slug'],
                'excerpt' => $t['excerpt'] ?? null,
                'content' => $translatedContent,
                'meta_title' => $t['meta_title'] ?? null,
                'meta_description' => $t['meta_description'] ?? null,
                'og_title' => $t['og_title'] ?? null,
                'og_description' => $t['og_description'] ?? null,
                'ai_summary' => $t['ai_summary'] ?? null,
            ]
        );

        // Mirror into content_ideas.generated_article.{locale} so Finalize UI
        // (which reads the JSON blob, not post_translations) stays in sync.
        // Without this, the admin tab shows "Belum diterjemahkan" for ideas
        // whose blog post is already translated via the auto-publish path.
        //
        // Two safety rails:
        //   1. Never mirror into the primary language slot — generated_article.id
        //      is the raw authored source of truth, which must not be clobbered
        //      by the rendered blog version.
        //   2. Strip `<figure>` blocks before writing. post_translations stores
        //      figure-injected content; generated_article stores raw body.
        //      Finalize re-injects images from image_prompts at render time, so
        //      keeping figures here would double-render.
        $idea = ContentIdea::where('result_post_id', $post->id)->first();
        if ($idea) {
            $article = $idea->generated_article ?? [];
            $primaryLang = $article['language'] ?? 'id';
            if ($locale !== $primaryLang) {
                $rawContent = \App\Support\HtmlFigureStripper::strip($translatedContent);
                $article[$locale] = array_merge($article[$locale] ?? [], [
                    'title' => $t['title'],
                    'content' => $rawContent,
                    'excerpt' => $t['excerpt'] ?? null,
                    'meta_title' => $t['meta_title'] ?? null,
                    'meta_description' => $t['meta_description'] ?? null,
                    'og_title' => $t['og_title'] ?? null,
                    'og_description' => $t['og_description'] ?? null,
                    'ai_summary' => $t['ai_summary'] ?? null,
                ]);
                $article['translation_status'] = 'done';
                $article['translation_completed_at'] = now()->toIso8601String();
                unset($article['translation_error']);
                $idea->generated_article = $article;
                $idea->save();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'language' => $locale,
                'translation_id' => $pt->id,
            ],
            'message' => 'Translation saved.',
        ]);
    }

    /**
     * Automation: mark post translation complete (flips translation_pending=false).
     */
    public function markTranslationComplete($id, Request $request): JsonResponse
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $post->update([
            'translation_pending' => false,
            'last_translation_attempt' => now(),
        ]);

        // Gate Completed transition on translation readiness. The idea stayed
        // at images_ready through publish() when use_translate_phase=true;
        // now that EN translation landed, this is the "truly done" edge.
        // Telegram publish-success notif piggy-backs here so readers get the
        // alert with a working URL to the finished (multilingual) article.
        $idea = ContentIdea::where('result_post_id', $post->id)->first();
        if ($idea && $idea->status !== 'completed') {
            $currentEnum = \App\Enums\ContentIdeaStatus::from($idea->status);
            if ($currentEnum->canTransitionTo(\App\Enums\ContentIdeaStatus::Completed)) {
                $idea->transitionTo(
                    \App\Enums\ContentIdeaStatus::Completed,
                    'translation_complete',
                    ['result_post_id' => $post->id]
                );
                app(\App\Services\TelegramNotifier::class)->notifyPublishSuccess($post);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'translation_pending' => false,
            ],
        ]);
    }

    /**
     * Automation: progress callback from /article-translate skill.
     * Lightweight — just logs. Post-level progress does not need DB persistence
     * (translation is synchronous-ish from user perspective).
     */
    public function postProgress($id, Request $request): JsonResponse
    {
        $request->validate([
            'step' => 'required|string|max:100',
            'percentage' => 'required|integer|min:0|max:100',
            'message' => 'nullable|string|max:500',
        ]);

        Log::info('[Post translate] progress', [
            'post_id' => $id,
            'step' => $request->input('step'),
            'percentage' => $request->input('percentage'),
            'message' => $request->input('message'),
        ]);

        return response()->json(['success' => true]);
    }

    // ========================================================================
    // PROGRESS TRACKING
    // ========================================================================

    /**
     * Get real-time progress for an idea being processed.
     */
    public function getProgress($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        // Check if process is still alive (if we have a PID)
        $processAlive = true;
        if ($idea->process_pid && in_array($idea->status, ['researching', 'generating_images'])) {
            $processAlive = $this->articleGen->isProcessRunning($idea->process_pid);
            if (!$processAlive && $idea->progress_percentage < 100) {
                // Process died without completing
                $idea->update([
                    'current_step' => 'failed',
                    'progress_log' => array_merge($idea->progress_log ?? [], [[
                        'timestamp' => now()->toISOString(),
                        'step' => 'failed',
                        'percentage' => $idea->progress_percentage,
                        'message' => 'Process terminated unexpectedly (PID: ' . $idea->process_pid . ')',
                    ]]),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $idea->id,
                'status' => $idea->status,
                'progress_percentage' => $idea->progress_percentage,
                'current_step' => $idea->current_step,
                'progress_log' => $idea->progress_log ?? [],
                'process_alive' => $processAlive,
            ],
        ]);
    }

    // ========================================================================
    // CONTENT ENGINE PROXY (kept for backward compatibility)
    // ========================================================================

    public function healthCheck(): JsonResponse
    {
        // Check CLI-based system health instead of microservice
        $driver = config('services.article_generation.driver', 'ssh');
        $host = config('services.article_generation.ssh_host', '');

        return response()->json([
            'success' => true,
            'data' => [
                'healthy' => true,
                'driver' => $driver,
                'mode' => 'cli',
                'host' => $driver === 'ssh' ? $host : 'localhost',
                'message' => 'Article generation via Claude Code CLI',
            ],
        ]);
    }

    public function listWorkflows(): JsonResponse
    {
        // Return workflow data from content_ideas table instead of microservice
        $ideas = ContentIdea::whereNotNull('workflows')
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        $workflows = [];
        foreach ($ideas as $idea) {
            foreach ($idea->workflows ?? [] as $wf) {
                $workflows[] = array_merge($wf, [
                    'topic' => $idea->title,
                    'idea_id' => $idea->id,
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $workflows]);
    }

    public function getWorkflowStatus($id): JsonResponse
    {
        // Look up by idea ID
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Workflow not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $idea->id,
                'status' => $idea->status,
                'progress' => $idea->progress_percentage,
                'current_step' => $idea->current_step,
                'workflows' => $idea->workflows,
            ],
        ]);
    }

    // ========================================================================
    // INTERNAL: Trending-score sort helper
    // ========================================================================

    /**
     * Score an idea for "trending priority" sort. Higher = closer to row 1.
     * Weights: heat tier (dominant) > trusted publisher count > pub_date recency >
     * created_at recency as tiebreaker for manual ideas with no trend metadata.
     *
     * Values pulled from source_data JSON populated by TrendingTopicService
     * during import. Manual ideas (no source_data) score 0 + created_at, so
     * they fall below any trending-import item.
     */
    private function computeTrendingScore(ContentIdea $idea): float
    {
        $data = is_array($idea->source_data) ? $idea->source_data : [];

        $heat = strtolower((string) ($data['heat'] ?? ''));
        $heatBonus = match ($heat) {
            'hot' => 10000,
            'trending' => 5000,
            'standard' => 500,
            default => 0,
        };

        $trustedCount = (int) ($data['trusted_publisher_count'] ?? $data['publisher_count'] ?? 0);
        $coverageBonus = min($trustedCount, 10) * 100;

        // Fresher news wins. pub_date is RFC-822; convert to age in hours.
        $pubTs = !empty($data['pub_date']) ? strtotime($data['pub_date']) : 0;
        $recencyBonus = 0;
        if ($pubTs > 0) {
            $ageHours = max(0, (time() - $pubTs) / 3600);
            // Linear decay over 7 days, then flat zero.
            $recencyBonus = max(0, 168 - $ageHours) * 2; // up to 336
        }

        // Tiebreaker only: normalize created_at to 0..100 based on how recent
        // it is within the last 30 days. Keeps manual ideas below any trending
        // import (which starts at +500 for standard, +5000 for trending).
        $createdTs = $idea->created_at ? $idea->created_at->timestamp : 0;
        $createdBonus = 0;
        if ($createdTs > 0) {
            $ageDays = max(0, (time() - $createdTs) / 86400);
            $createdBonus = max(0, 30 - $ageDays) * (100 / 30); // 0..100
        }

        return $heatBonus + $coverageBonus + $recencyBonus + $createdBonus;
    }

    // ========================================================================
    // INTERNAL: Sync idea status from process/progress
    // ========================================================================

    private function syncIdeaStatus(ContentIdea $idea): void
    {
        // For CLI-based generation, status is updated via progress callbacks
        // Just check if the process is still alive
        if ($idea->process_pid) {
            $alive = $this->articleGen->isProcessRunning($idea->process_pid);
            if (!$alive && $idea->progress_percentage < 100) {
                Log::warning('[ContentIdea] Process died for idea ' . $idea->id, [
                    'pid' => $idea->process_pid,
                    'progress' => $idea->progress_percentage,
                ]);
            }
        }
    }

    // ========================================================================
    // GATE 2: IMAGE PROMPT AUTHORING (split phase)
    // ========================================================================

    /**
     * Automation callback: /article-images skill saves authored image_prompts[].
     * Merges into generated_article without touching article body or scores.
     */
    public function saveImagePrompts($id, Request $request): JsonResponse
    {
        $request->validate([
            'image_prompts' => 'required|array|min:1',
            'image_prompts.*.type' => 'required|string|in:cover,inline',
            'image_prompts.*.concept' => 'required|string',
            'image_prompts.*.prompt' => 'required|string',
            'image_prompts.*.insert_after_heading' => 'nullable|string',
            'idempotency_key' => 'nullable|string',
        ]);

        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $article = $idea->generated_article ?? [];
        $article['image_prompts'] = $request->input('image_prompts');
        $idea->update(['generated_article' => $article]);

        return response()->json([
            'success' => true,
            'data' => ['image_prompts_count' => count($article['image_prompts'])],
            'message' => 'Image prompts saved.',
        ]);
    }

    /**
     * Admin: update a single section's image_concept before regeneration.
     */
    public function updateImageConcept($id, Request $request): JsonResponse
    {
        $request->validate([
            'section_position' => 'required|integer',
            'image_concept' => 'nullable|string',
        ]);

        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $article = $idea->generated_article ?? [];
        $sections = data_get($article, 'prep_data.outline.sections', []);
        if (empty($sections)) {
            return response()->json(['success' => false, 'message' => 'Outline not available for this idea.'], 409);
        }

        $targetPosition = $request->input('section_position');
        $newConcept = $request->input('image_concept');
        $found = false;
        foreach ($sections as $idx => $section) {
            if ((int) ($section['position'] ?? -1) === (int) $targetPosition) {
                $sections[$idx]['image_concept'] = $newConcept;
                $found = true;
                break;
            }
        }

        if (!$found) {
            return response()->json([
                'success' => false,
                'message' => "Section position {$targetPosition} not found in outline.",
            ], 404);
        }

        data_set($article, 'prep_data.outline.sections', $sections);
        $idea->update(['generated_article' => $article]);

        return response()->json([
            'success' => true,
            'data' => [
                'updated_position' => (int) $targetPosition,
                'new_concept' => $newConcept,
            ],
            'message' => 'Image concept updated.',
        ]);
    }

    /**
     * Rewrite a single segment's Visual Direction so it matches the uploaded
     * face reference. Called from the frontend Apply & Generate flow when a
     * face_ref is present — prevents demographic contradiction between VD
     * text and the reference image (e.g. VD says "young woman" but ref is
     * a bald older man, which GeminiGen resolves toward the text).
     */
    public function rewriteSegmentVd($id, Request $request): JsonResponse
    {
        $request->validate([
            'segment_index' => 'required|integer|min:0',
            'face_ref_url' => 'required|string|max:2000',
        ]);

        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $segmentIndex = (int) $request->input('segment_index');
        $faceRefUrl = (string) $request->input('face_ref_url');

        if (str_starts_with($faceRefUrl, 'blob:')) {
            return response()->json([
                'success' => false,
                'message' => 'Face reference URL must be a persisted storage URL, not a browser blob.',
            ], 422);
        }

        $article = $idea->generated_article ?? [];
        $imagePrompts = $article['image_prompts'] ?? [];
        if (!isset($imagePrompts[$segmentIndex])) {
            return response()->json([
                'success' => false,
                'message' => "Segment index {$segmentIndex} not found.",
            ], 404);
        }

        $segment = $imagePrompts[$segmentIndex];
        $originalVd = (string) ($segment['visual_direction'] ?? $segment['prompt'] ?? '');
        if (trim($originalVd) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Segment has no visual direction to rewrite.',
            ], 422);
        }

        $segmentContext = [
            'label' => $segment['type'] === 'cover' ? 'COVER' : ('BODY-' . $segmentIndex),
            'concept' => (string) ($segment['concept'] ?? ''),
            'style' => (string) ($segment['style'] ?? ''),
        ];

        $result = $this->articleGen->rewriteVisualDirectionForFace($originalVd, $faceRefUrl, $segmentContext);

        if (!$result['success']) {
            Log::warning('[ContentIdea] VD rewrite failed', [
                'idea_id' => $idea->id,
                'segment_index' => $segmentIndex,
                'error' => $result['error'],
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to rewrite Visual Direction: ' . ($result['error'] ?? 'Unknown error'),
            ], 502);
        }

        if (empty($imagePrompts[$segmentIndex]['visual_direction_original'])) {
            $imagePrompts[$segmentIndex]['visual_direction_original'] = $originalVd;
        }
        $imagePrompts[$segmentIndex]['visual_direction'] = $result['rewritten_vd'];
        $article['image_prompts'] = $imagePrompts;
        $idea->generated_article = $article;
        $idea->save();

        return response()->json([
            'success' => true,
            'data' => [
                'segment_index' => $segmentIndex,
                'original_vd' => $originalVd,
                'new_vd' => $result['rewritten_vd'],
            ],
            'message' => 'Visual Direction rewritten to match face reference.',
        ]);
    }

    /**
     * Admin: regenerate image prompts for all or a filtered list of sections.
     * Triggers /article-images skill with optional only-sections filter.
     */
    public function regenerateImagePrompts($id, Request $request): JsonResponse
    {
        $request->validate([
            'sections' => 'nullable|array',
            'sections.*' => 'integer',
        ]);

        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        // Includes 'completed' so operators can fix wrong covers post-publish
        // without manually reverting status. Common case: cover ships with the
        // creator's face when the article was about a public figure (entity
        // detection missed the subject) — operator hits Regen Prompt to re-run
        // /article-images NER + Wikidata lookup. Without 'completed' in this
        // whitelist the button silently 409s and operators end up clicking
        // Generate AI repeatedly, which dispatches with the same broken VD.
        // Mirror behavior to ProcessPendingImages::syncToContentIdea + same
        // rationale documented in 68001a38.
        if (!in_array($idea->status, ['article_ready', 'images_ready', 'generating_images', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Regenerate requires article_ready, images_ready, generating_images, or completed status.',
            ], 409);
        }

        $onlySections = $request->input('sections', []);
        $idempotencyKey = Str::uuid()->toString();
        $result = $this->articleGen->triggerImages($idea->id, $idempotencyKey, $onlySections);

        // Revert completed → generating_images so the admin table reflects
        // "in-progress" while the new prompts render. Other start statuses
        // (article_ready, images_ready, generating_images) stay where they
        // are — only completed needs the explicit transition. No $extra
        // update args — the `source` column is an enum, and `reason` already
        // captures intent for the pipeline_state_log audit trail.
        if ($idea->status === 'completed') {
            $idea->transitionTo(
                \App\Enums\ContentIdeaStatus::GeneratingImages,
                'admin_regenerate_images_from_completed'
            );
        }

        $idea->update([
            'process_pid' => $result['pid'] ?? null,
            'current_step' => 'authoring_image_prompts',
            'progress_percentage' => 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $idempotencyKey,
                'pid' => $result['pid'] ?? null,
                'scope' => empty($onlySections) ? 'all' : 'filtered',
                'sections' => $onlySections,
            ],
            'message' => 'Image prompt regeneration triggered.',
        ]);
    }

    /**
     * Translate the primary-language article to English in-place on the idea.
     * Synchronous — blocks for ~30-60s while Claude CLI runs via SSH. Writes
     * status/error fields on generated_article so the frontend can poll or
     * reflect the outcome on refresh.
     */
    public function translateArticle($id, Request $request): JsonResponse
    {
        // Request returns instantly now (dispatch + 202); heavy work runs in a queued job.
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        $article = $idea->generated_article ?? [];
        $primaryLang = $article['language'] ?? 'id';
        $targetLang = $primaryLang === 'id' ? 'en' : 'id';
        $primary = $article[$primaryLang] ?? [];

        if (empty($primary['title']) && empty($primary['content'])) {
            return response()->json([
                'success' => false,
                'message' => 'Primary-language article content is missing; nothing to translate.',
            ], 422);
        }

        // Already translated? en.content exists and differs from primary
        $existing = $article[$targetLang]['content'] ?? '';
        if ($existing !== '' && $existing !== ($primary['content'] ?? '')) {
            return response()->json([
                'success' => true,
                'data' => $idea->fresh(),
                'message' => 'Already translated.',
            ]);
        }

        // Mark status so polling / subsequent checks see the in-flight state,
        // then dispatch async. The translate is an SSH → Claude CLI call that
        // runs 90-300s+ — far past Cloudflare's ~100s edge timeout, which made
        // the old synchronous path return 524 on every real translate. Frontend
        // polls generated_article.translation_status until done/failed.
        $article['translation_status'] = 'translating';
        $article['translation_started_at'] = now()->toIso8601String();
        unset($article['translation_error']);
        $idea->generated_article = $article;
        $idea->save();

        \App\Jobs\TranslateContentIdea::dispatch($idea->id);

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Translation started.',
            'translation_status' => 'translating',
        ], 202);
    }

}
