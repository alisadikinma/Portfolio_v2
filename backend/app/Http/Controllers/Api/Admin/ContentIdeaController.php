<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\ArticleGenerationService;
use App\Services\ContentEngineService;
use App\Services\TrendingTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentIdeaController extends Controller
{
    public function __construct(
        private ArticleGenerationService $articleGen,
        private ContentEngineService $engine,
        private TrendingTopicService $trending
    ) {}

    /**
     * List ideas with optional filters.
     * Auto-syncs status for any 'researching' or 'generating_images' ideas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContentIdea::query();

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

        $perPage = min((int) $request->query('per_page', 15), 50);
        $ideas = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Auto-sync: check workflow completion for in-progress ideas
        foreach ($ideas as $idea) {
            if (in_array($idea->status, ['researching', 'generating_images'])) {
                $this->syncIdeaStatus($idea);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $ideas->items(),
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

        $idea->update(['status' => 'archived']);
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

        $idea->update(['status' => 'draft']);
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

        $idea->update([
            'status' => 'draft',
            'research_data' => null,
            'generated_article' => null,
            'generated_images' => null,
            'image_instructions' => null,
            'image_references' => null,
        ]);

        return response()->json(['success' => true, 'data' => $idea->fresh(), 'message' => 'Reverted to draft.']);
    }

    // ========================================================================
    // TRENDING TOPICS
    // ========================================================================

    /**
     * Pull trending topics from all sources.
     */
    public function pullTrending(Request $request): JsonResponse
    {
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
     * Import selected trending topics as content ideas.
     */
    public function importTrending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topics' => 'required|array|min:1',
            'topics.*.title' => 'required|string|max:500',
            'topics.*.source' => 'nullable|string',
        ]);

        $imported = [];
        foreach ($validated['topics'] as $topic) {
            $imported[] = ContentIdea::create([
                'title' => $topic['title'],
                'source' => $topic['source'] ?? 'manual',
                'status' => 'draft',
                'source_data' => $topic,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $imported,
            'message' => count($imported) . ' topic(s) imported.',
        ], 201);
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
        ]);

        $idea->update([
            'output_types' => ['blog_article'],
            'languages' => $validated['languages'],
            'instructions' => $validated['instructions'] ?? null,
            'status' => 'researching',
            'progress_percentage' => 0,
            'current_step' => 'initializing',
            'progress_log' => [[
                'timestamp' => now()->toISOString(),
                'step' => 'initializing',
                'percentage' => 0,
                'message' => 'Article generation triggered',
            ]],
        ]);

        // Use split pipeline (prep→write→score) when refs are configured, else fallback to single /article-gen
        $useSplitPipeline = !empty(config('services.article_generation.refs_prep'));

        if ($useSplitPipeline) {
            $result = $this->articleGen->triggerPrep($idea->id, [
                'topic' => $idea->title,
                'languages' => $validated['languages'],
                'instructions' => $validated['instructions'] ?? '',
            ]);
        } else {
            $result = $this->articleGen->triggerGeneration($idea->id, [
                'topic' => $idea->title,
                'languages' => $validated['languages'],
                'instructions' => $validated['instructions'] ?? '',
            ]);
        }

        if ($result['success']) {
            $idea->update([
                'process_pid' => $result['pid'],
                'workflows' => [[
                    'type' => 'blog_article',
                    'driver' => 'claude_cli',
                    'pipeline' => $useSplitPipeline ? 'split' : 'single',
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

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => $result['success']
                ? ($useSplitPipeline ? 'Split pipeline started (prep → write → score).' : 'Article generation started via CLI.')
                : 'Generation trigger failed, but idea is in researching state.',
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

        $allowedStatuses = ['article_ready', 'images_ready'];
        // Also allow regeneration from failed researching state
        if ($idea->status === 'researching' && $idea->current_step === 'failed') {
            $allowedStatuses[] = 'researching';
        }
        if (!in_array($idea->status, $allowedStatuses)) {
            return response()->json(['success' => false, 'message' => 'Can only regenerate from article_ready, images_ready, or failed state.'], 422);
        }

        $languages = $idea->languages ?? ['en'];
        $instructions = $request->input('instructions', $idea->instructions);
        $previousStatus = $idea->status;

        // Trigger generation FIRST — only clear data after success
        $result = $this->articleGen->triggerGeneration($idea->id, [
            'topic' => $idea->title,
            'languages' => $languages,
            'instructions' => $instructions ?? '',
        ]);

        if ($result['success']) {
            // Only wipe old article after trigger succeeds
            $idea->update([
                'generated_article' => null,
                'generated_images' => null,
                'image_instructions' => null,
                'image_references' => null,
                'status' => 'researching',
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
        $idea->status = 'generating_images';
        $idea->save();

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
            $idea->generated_article = $request->input('generated_article');
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
        ]);

        $segmentIndex = $request->input('segment_index');
        $model = $request->input('model', 'nano-banana-2');
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

            // Retry-in-place: reuse a failed or orphaned slot before appending. An
            // orphan is status=generating with no job_uuid — left over when the
            // queue call or save crashed before the UUID was persisted.
            $reuseIndex = null;
            foreach ($variations as $vi => $v) {
                $vStatus = $v['status'] ?? '';
                $isFailed = $vStatus === 'failed';
                $isOrphan = $vStatus === 'generating' && empty($v['job_uuid']);
                if ($isFailed || $isOrphan) {
                    $reuseIndex = $vi;
                    break;
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

        // Set status to generating_images if not already
        if ($idea->status === 'article_ready') {
            $idea->status = 'generating_images';
        }
        $idea->save();

        // Call GeminiGen via ImageGenerationService
        $imageService = app(\App\Services\ImageGenerationService::class);
        $uuid = $imageService->queue(
            postId: null,
            prompt: $prompt,
            type: $segmentIndex === 0 ? 'hero' : 'inline',
            insertAfterHeading: null,
            model: $model,
            aspectRatio: $aspectRatio,
            style: $style,
            faceRefs: array_merge($faceRefs, $brandRefUrls),
            styleRefs: $styleRefs,
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
     * GATE 2: Approve images and publish article to blog.
     */
    public function approveAndPublish($id): JsonResponse
    {
        $idea = ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
        }

        if (!in_array($idea->status, ['article_ready', 'images_ready'])) {
            return response()->json(['success' => false, 'message' => 'Cannot publish in current status.'], 422);
        }

        $article = $idea->generated_article ?? [];

        // Compact variations to selected-only and collect non-selected URLs for
        // file cleanup after Post is successfully created. This is the actual
        // cleanup gate — previously done prematurely at Approve & Continue in
        // the frontend Images step. See docs/plans/2026-04-17-content-engine-preview-4bugs-plan.md
        $imagePrompts = $article['image_prompts'] ?? [];
        $urlsToDelete = [];
        foreach ($imagePrompts as $i => $prompt) {
            $variations = $prompt['variations'] ?? [];
            if (empty($variations)) continue;

            $selectedIdx = $prompt['selected_variation'] ?? 0;
            $selectedVar = $variations[$selectedIdx] ?? $variations[0] ?? null;
            $selectedUrl = $selectedVar['url'] ?? ($prompt['generated_url'] ?? null);

            foreach ($variations as $vi => $v) {
                if ($vi !== $selectedIdx && !empty($v['url']) && $v['url'] !== $selectedUrl) {
                    $urlsToDelete[] = $v['url'];
                }
            }

            if ($selectedVar) {
                $imagePrompts[$i]['variations'] = [$selectedVar];
                $imagePrompts[$i]['selected_variation'] = 0;
                $imagePrompts[$i]['generated_url'] = $selectedUrl;
            }
        }
        $article['image_prompts'] = $imagePrompts;
        $idea->generated_article = $article;
        $idea->save();

        $primaryLang = $article['language'] ?? 'id';
        $title = $article['title'] ?? $idea->title;
        $content = $article['content'] ?? '';
        $excerpt = $article['excerpt'] ?? null;
        // Prefer explicit cover-type prompt; fall back to index 0 for legacy prompts.
        $coverPrompt = collect($imagePrompts)->firstWhere('type', 'cover');
        $featuredImage = data_get($idea->generated_images, '0.url')
            ?? data_get($idea->generated_images, '0')
            ?? data_get($coverPrompt, 'generated_url')
            ?? data_get($imagePrompts, '0.generated_url')
            ?? null;

        // Resolve category_id: idea.niche → Category lookup, fallback to first category
        $categoryId = null;
        if (!empty($idea->niche)) {
            $category = \App\Models\Category::where('slug', Str::slug($idea->niche))
                ->orWhere('name', $idea->niche)
                ->first();
            $categoryId = $category?->id;
        }
        if (!$categoryId) {
            $categoryId = \App\Models\Category::orderBy('id')->value('id');
        }
        if (!$categoryId) {
            return response()->json([
                'success' => false,
                'message' => 'No category available. Create at least one category before publishing.',
            ], 409);
        }

        // Build unique slug: append short idea id if slug collides
        $baseSlug = Str::slug($title);
        $uniqueSlug = $baseSlug;
        $existing = Post::where('slug', $uniqueSlug)->where('source_idea_id', '!=', $idea->id)->first();
        if ($existing) {
            $uniqueSlug = $baseSlug . '-' . $idea->id;
        }

        // UPSERT Post — title/content/excerpt live in post_translations, NOT posts table
        $post = Post::updateOrCreate(
            ['source_idea_id' => $idea->id],
            [
                'category_id' => $categoryId,
                'slug' => $uniqueSlug,
                'featured_image' => $featuredImage,
                'published' => true,
                'published_at' => now(),
                'seo_score' => data_get($article, 'seo_analysis.score'),
                'schema_markup' => data_get($article, 'schema_markup'),
                'faq_schema' => data_get($article, 'faq_schema'),
                'og_image' => data_get($article, 'og_image') ?? $featuredImage,
                'translation_pending' => false,
                'translation_attempts' => 0,
            ]
        );

        // UPSERT primary-language translation
        PostTranslation::updateOrCreate(
            ['post_id' => $post->id, 'language' => $primaryLang],
            [
                'title' => $title,
                'slug' => $uniqueSlug,
                'excerpt' => $excerpt,
                'content' => $content,
                'meta_title' => data_get($article, 'meta_title'),
                'meta_description' => data_get($article, 'meta_description'),
                'og_title' => data_get($article, 'og_title'),
                'og_description' => data_get($article, 'og_description'),
                'ai_summary' => data_get($article, 'ai_summary'),
            ]
        );

        // Delete non-selected variation files from storage. Runs after the Post
        // is safely written so a mid-flight failure doesn't orphan the selected
        // variant. Failures here are logged but non-fatal — the post is already
        // created and the orphaned files are recoverable via a sweep job.
        if (!empty($urlsToDelete)) {
            $storageBase = url('/storage/');
            $deleted = 0;
            foreach ($urlsToDelete as $imageUrl) {
                if (!str_starts_with($imageUrl, $storageBase)) continue;
                $relativePath = str_replace($storageBase . '/', '', $imageUrl);
                try {
                    if (Storage::disk('public')->exists($relativePath)) {
                        Storage::disk('public')->delete($relativePath);
                        $deleted++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('[ContentIdea] Failed to delete variation file', [
                        'path' => $relativePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            if ($deleted > 0) {
                Log::info('[ContentIdea] Cleaned up variation files on publish', [
                    'idea_id' => $idea->id,
                    'post_id' => $post->id,
                    'deleted_count' => $deleted,
                    'requested_count' => count($urlsToDelete),
                ]);
            }
        }

        // Gate the translate phase behind feature flag
        $translationPending = false;
        $targetLocales = array_values(array_diff($idea->languages ?? [$primaryLang], [$primaryLang]));

        if (
            config('services.article_generation.use_translate_phase')
            && !empty($targetLocales)
        ) {
            $targetLocale = $targetLocales[0];
            $idempotencyKey = (string) Str::uuid();
            $result = $this->articleGen->triggerTranslate($post->id, $idempotencyKey, $targetLocale);

            $post->update([
                'translation_pending' => true,
                'translation_attempts' => 1,
                'last_translation_attempt' => now(),
            ]);
            $translationPending = true;

            Log::info('[ContentIdea] Translation triggered', [
                'idea_id' => $idea->id,
                'post_id' => $post->id,
                'target_locale' => $targetLocale,
                'pid' => $result['pid'] ?? null,
            ]);
        }

        $idea->update([
            'status' => 'completed',
            'result_post_id' => $post->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'idea' => $idea->fresh(),
                'published_post_id' => $post->id,
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

        if (!in_array($idea->status, ['article_ready', 'images_ready', 'generating_images'])) {
            return response()->json([
                'success' => false,
                'message' => 'Regenerate requires article_ready, images_ready, or generating_images status.',
            ], 409);
        }

        $onlySections = $request->input('sections', []);
        $idempotencyKey = Str::uuid()->toString();
        $result = $this->articleGen->triggerImages($idea->id, $idempotencyKey, $onlySections);

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
}
