<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentIdea;
use App\Services\ContentEngineService;
use App\Services\TrendingTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContentIdeaController extends Controller
{
    public function __construct(
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

        $ideas = $query->orderBy('created_at', 'desc')->get();

        // Auto-sync: check workflow completion for in-progress ideas
        foreach ($ideas as $idea) {
            if (in_array($idea->status, ['researching', 'generating_images'])) {
                $this->syncIdeaStatus($idea);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $ideas,
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
     * Start article generation: research + write full article.
     * Triggers blog_article workflow on Content Engine.
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
        ]);

        try {
            $result = $this->engine->createWorkflow('blog_article', [
                'topic' => $idea->title,
                'niche' => $idea->niche,
                'languages' => $validated['languages'],
                'instructions' => $validated['instructions'] ?? '',
                'idea_id' => $idea->id,
            ]);

            $idea->update([
                'workflows' => [[
                    'type' => 'blog_article',
                    'workflow_id' => $result['id'] ?? null,
                    'status' => 'pending',
                    'created_at' => now()->toISOString(),
                ]],
            ]);
        } catch (\Exception $e) {
            Log::warning('[ContentIdea] Blog workflow creation failed: ' . $e->getMessage());
            // Don't block — user can retry or Content Engine may be temporarily down
        }

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Article generation started.',
        ]);
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

        // Trigger image generation via Content Engine
        try {
            $article = $idea->generated_article ?? [];
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
    // CONTENT ENGINE PROXY
    // ========================================================================

    public function healthCheck(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->engine->healthCheck()]);
    }

    public function listWorkflows(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->engine->listWorkflows()]);
    }

    public function getWorkflowStatus($id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->engine->getWorkflowStatus($id)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Workflow not found.'], 404);
        }
    }

    // ========================================================================
    // INTERNAL: Sync idea status from Content Engine workflow
    // ========================================================================

    private function syncIdeaStatus(ContentIdea $idea): void
    {
        $workflows = $idea->workflows ?? [];
        if (empty($workflows)) return;

        $lastWorkflow = end($workflows);
        $workflowId = $lastWorkflow['workflow_id'] ?? null;
        if (!$workflowId) return;

        try {
            $status = $this->engine->getWorkflowStatus($workflowId);
            $workflowStatus = $status['status'] ?? 'pending';

            if ($workflowStatus === 'completed') {
                // Update workflow record
                $lastKey = array_key_last($workflows);
                $workflows[$lastKey]['status'] = 'completed';
                $idea->workflows = $workflows;

                if ($idea->status === 'researching') {
                    // Article generation complete — store output
                    $idea->generated_article = $status['output_data'] ?? $status['result'] ?? null;
                    $idea->status = 'article_ready';
                } elseif ($idea->status === 'generating_images') {
                    $idea->generated_images = $status['output_data'] ?? $status['result'] ?? null;
                    $idea->status = 'images_ready';
                }

                $idea->save();
            } elseif ($workflowStatus === 'failed') {
                $lastKey = array_key_last($workflows);
                $workflows[$lastKey]['status'] = 'failed';
                $workflows[$lastKey]['error'] = $status['error'] ?? 'Unknown error';
                $idea->workflows = $workflows;
                $idea->save();
            }
        } catch (\Exception $e) {
            // Engine unreachable, skip sync
        }
    }
}
