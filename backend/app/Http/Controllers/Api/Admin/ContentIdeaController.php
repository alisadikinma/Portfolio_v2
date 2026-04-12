<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentIdea;
use App\Services\ContentEngineService;
use App\Services\TrendingTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentIdeaController extends Controller
{
    public function __construct(
        private ContentEngineService $engine,
        private TrendingTopicService $trending
    ) {}

    /**
     * List ideas with optional filters.
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
            $search = $request->query('search');
            $query->where('title', 'like', "%{$search}%");
        }

        $ideas = $query->orderBy('created_at', 'desc')->get();

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
            return response()->json([
                'success' => false,
                'message' => 'Content idea not found.',
            ], 404);
        }

        if ($idea->status === 'generating') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update an idea while content is being generated.',
            ], 422);
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
            return response()->json([
                'success' => false,
                'message' => 'Content idea not found.',
            ], 404);
        }

        if ($idea->status === 'generating') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an idea while content is being generated.',
            ], 422);
        }

        $idea->delete();

        return response()->json([
            'success' => true,
            'message' => 'Content idea deleted successfully.',
        ]);
    }

    /**
     * Archive a content idea.
     */
    public function archive($id): JsonResponse
    {
        $idea = ContentIdea::find($id);

        if (!$idea) {
            return response()->json([
                'success' => false,
                'message' => 'Content idea not found.',
            ], 404);
        }

        $idea->update(['status' => 'archived']);

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Content idea archived.',
        ]);
    }

    /**
     * Restore an archived idea back to draft.
     */
    public function restore($id): JsonResponse
    {
        $idea = ContentIdea::find($id);

        if (!$idea) {
            return response()->json([
                'success' => false,
                'message' => 'Content idea not found.',
            ], 404);
        }

        $idea->update(['status' => 'draft']);

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Content idea restored to draft.',
        ]);
    }

    /**
     * Revert a researched idea back to draft.
     */
    public function revertToDraft($id): JsonResponse
    {
        $idea = ContentIdea::find($id);

        if (!$idea) {
            return response()->json([
                'success' => false,
                'message' => 'Content idea not found.',
            ], 404);
        }

        if ($idea->status !== 'researched') {
            return response()->json([
                'success' => false,
                'message' => 'Only researched ideas can be reverted to draft.',
            ], 422);
        }

        $idea->update([
            'status' => 'draft',
            'research_data' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Content idea reverted to draft.',
        ]);
    }

    /**
     * Pull trending topics from all sources.
     */
    public function pullTrending(Request $request): JsonResponse
    {
        $source = $request->query('source');

        $trends = $this->trending->getAllTrends($source);

        if (!$source || $source === 'instagram') {
            $instagramTrends = $this->engine->getInstagramTrending();
            $formatted = array_map(function ($item) {
                return [
                    'title' => $item['caption'] ?? $item['title'] ?? 'Untitled',
                    'source' => 'instagram',
                    'score' => $item['score'] ?? 60,
                    'description' => $item['description'] ?? '',
                ];
            }, $instagramTrends);
            $trends = array_merge($trends, $formatted);
        }

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
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
            'message' => count($imported) . ' trending topic(s) imported as content ideas.',
        ], 201);
    }

    /**
     * Start research phase for a content idea.
     */
    public function startResearch($id, Request $request): JsonResponse
    {
        $idea = ContentIdea::find($id);

        if (!$idea) {
            return response()->json([
                'success' => false,
                'message' => 'Content idea not found.',
            ], 404);
        }

        $validated = $request->validate([
            'output_types' => 'required|array|min:1',
            'output_types.*' => 'in:carousel_rebrand,blog_article,video_social,video_promo',
            'languages' => 'required|array|min:1',
            'languages.*' => 'in:en,id',
            'instructions' => 'nullable|string',
        ]);

        $idea->update([
            'output_types' => $validated['output_types'],
            'languages' => $validated['languages'],
            'instructions' => $validated['instructions'] ?? null,
            'status' => 'researching',
        ]);

        try {
            $result = $this->engine->createWorkflow('research', [
                'topic' => $idea->title,
                'niche' => $idea->niche,
                'languages' => $validated['languages'],
            ]);

            $idea->update([
                'workflows' => [$result],
                'status' => 'researched',
            ]);
        } catch (\Exception $e) {
            $idea->update([
                'status' => 'researched',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Research phase initiated.',
        ]);
    }

    /**
     * Get research data for a content idea.
     */
    public function getResearch($id): JsonResponse
    {
        $idea = ContentIdea::find($id);

        if (!$idea) {
            return response()->json([
                'success' => false,
                'message' => 'Content idea not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $idea,
        ]);
    }

    /**
     * Approve and trigger content generation for all output types.
     */
    public function approveGenerate($id): JsonResponse
    {
        $idea = ContentIdea::find($id);

        if (!$idea) {
            return response()->json([
                'success' => false,
                'message' => 'Content idea not found.',
            ], 404);
        }

        if ($idea->status !== 'researched') {
            return response()->json([
                'success' => false,
                'message' => 'Only researched ideas can be approved for generation.',
            ], 422);
        }

        $idea->update(['status' => 'generating']);

        $workflowResults = [];
        foreach ($idea->output_types as $type) {
            try {
                $result = $this->engine->createWorkflow($type, [
                    'topic' => $idea->title,
                    'niche' => $idea->niche,
                    'languages' => $idea->languages,
                    'instructions' => $idea->instructions,
                ]);
                $workflowResults[] = [
                    'type' => $type,
                    'workflow_id' => $result['id'] ?? null,
                    'status' => 'pending',
                    'created_at' => now()->toISOString(),
                ];
            } catch (\Exception $e) {
                $workflowResults[] = [
                    'type' => $type,
                    'workflow_id' => null,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'created_at' => now()->toISOString(),
                ];
            }
        }

        $idea->workflows = $workflowResults;
        $idea->save();

        return response()->json([
            'success' => true,
            'data' => $idea->fresh(),
            'message' => 'Content generation workflows initiated.',
        ]);
    }

    /**
     * Check Content Engine health status.
     */
    public function healthCheck(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->engine->healthCheck(),
        ]);
    }

    /**
     * List all workflows from Content Engine.
     */
    public function listWorkflows(): JsonResponse
    {
        $workflows = $this->engine->listWorkflows();

        return response()->json([
            'success' => true,
            'data' => $workflows,
        ]);
    }

    /**
     * Get a specific workflow status from Content Engine.
     */
    public function getWorkflowStatus($id): JsonResponse
    {
        try {
            $status = $this->engine->getWorkflowStatus($id);

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workflow not found: ' . $e->getMessage(),
            ], 404);
        }
    }
}
