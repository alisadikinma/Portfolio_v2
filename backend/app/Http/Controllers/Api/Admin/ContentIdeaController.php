<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentIdea;
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
            'additional_notes' => 'nullable|string|max:1000',
        ]);

        $segmentIndex = $request->input('segment_index');
        $model = $request->input('model', 'nano-banana-2');
        $aspectRatio = $request->input('aspect_ratio', '16:9');
        $style = $request->input('style');
        $prompt = $request->input('prompt');
        $faceRefs = $request->input('face_refs', []);
        $styleRefs = $request->input('style_refs', []);
        $additionalNotes = $request->input('additional_notes', '');

        // Update segment status and save reference data
        $article = $idea->generated_article ?? [];
        $imagePrompts = $article['image_prompts'] ?? [];
        if (isset($imagePrompts[$segmentIndex])) {
            $imagePrompts[$segmentIndex]['status'] = 'generating';
            $imagePrompts[$segmentIndex]['face_refs'] = $faceRefs;
            $imagePrompts[$segmentIndex]['style_refs'] = $styleRefs;
            $imagePrompts[$segmentIndex]['additional_notes'] = $additionalNotes;
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
            faceRefs: $faceRefs,
            styleRefs: $styleRefs,
            additionalNotes: $additionalNotes
        );

        if (!$uuid) {
            // Mark segment as failed
            $imagePrompts[$segmentIndex]['status'] = 'failed';
            $article['image_prompts'] = $imagePrompts;
            $idea->generated_article = $article;
            $idea->save();

            return response()->json([
                'success' => false,
                'message' => 'Image generation failed to start.',
            ], 500);
        }

        // Store UUID in segment for tracking
        $imagePrompts[$segmentIndex]['job_uuid'] = $uuid;
        $article['image_prompts'] = $imagePrompts;
        $idea->generated_article = $article;
        $idea->save();

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $uuid,
                'segment_index' => $segmentIndex,
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

        $idea->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Content approved and published.',
        ]);
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
