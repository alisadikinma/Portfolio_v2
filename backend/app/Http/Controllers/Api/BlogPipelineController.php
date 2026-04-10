<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Category;
use App\Models\ImageGenerationJob;
use App\Services\TrendingTopicService;
use App\Services\ImageGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlogPipelineController extends Controller
{
    /**
     * GET /api/automation/blog/trending-topic
     */
    public function trendingTopic(TrendingTopicService $trendService): JsonResponse
    {
        $topic = $trendService->getBestTopic();

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'No unique trending AI/tech topic found today.',
                'data' => null,
            ]);
        }

        $categoryId = $trendService->suggestCategory($topic['title']);
        $category = Category::find($categoryId);

        return response()->json([
            'success' => true,
            'data' => [
                'topic' => $topic['title'],
                'description' => $topic['description'] ?? '',
                'source' => $topic['source'] ?? 'Google Trends',
                'matched_keyword' => $topic['matched_keyword'] ?? '',
                'suggested_category' => [
                    'id' => $categoryId,
                    'name' => $category?->name ?? 'Technology',
                    'slug' => $category?->slug ?? 'technology',
                ],
            ],
        ]);
    }

    /**
     * POST /api/automation/blog/save-draft
     *
     * Saves article immediately, queues image generation via GeminiGen (webhook-based).
     * Images are attached to the post asynchronously when GeminiGen webhook fires.
     */
    public function saveDraft(Request $request, ImageGenerationService $imageService): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'translations' => 'required|array',
            'translations.en' => 'required|array',
            'translations.en.title' => 'required|string|max:255',
            'translations.en.content' => 'required|string|min:100',
        ]);

        try {
            DB::beginTransaction();

            $translations = $request->input('translations');
            $enTitle = $translations['en']['title'];
            $slug = $request->input('slug') ?: Str::slug($enTitle);

            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (Post::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            // 1. Create post immediately (no waiting for images)
            $post = Post::create([
                'category_id' => $request->input('category_id'),
                'slug' => $slug,
                'tags' => $request->input('tags', []),
                'published' => false,
                'published_at' => null,
                'views' => 0,
                'index_follow' => true,
            ]);

            // 2. Create translations
            foreach ($translations as $lang => $transData) {
                if (empty($transData['title']) || empty($transData['content'])) continue;

                $transSlug = $transData['slug'] ?? ($lang === 'en' ? $slug : Str::slug($transData['title']));

                $post->translations()->create([
                    'language' => $lang,
                    'title' => $transData['title'],
                    'slug' => $transSlug,
                    'excerpt' => $transData['excerpt'] ?? Str::limit(strip_tags($transData['content']), 150),
                    'content' => $transData['content'],
                    'meta_title' => $transData['meta_title'] ?? null,
                    'meta_description' => $transData['meta_description'] ?? null,
                    'meta_keywords' => $transData['meta_keywords'] ?? null,
                    'og_title' => $transData['meta_title'] ?? $transData['title'],
                    'og_description' => $transData['meta_description'] ?? null,
                    'canonical_url' => $transData['canonical_url'] ?? null,
                    'ai_summary' => $transData['ai_summary'] ?? null,
                ]);
            }

            DB::commit();

            // 3. Queue image generation (fire-and-forget, webhook handles completion)
            $imageJobs = [];

            $heroPrompt = $request->input('hero_image_prompt');
            if ($heroPrompt) {
                $uuid = $imageService->queue($post->id, $heroPrompt, 'hero', null, 'imagen-pro', '16:9');
                if ($uuid) $imageJobs[] = ['type' => 'hero', 'uuid' => $uuid];
            }

            foreach ($request->input('inline_image_prompts', []) as $imgSpec) {
                $prompt = $imgSpec['prompt'] ?? '';
                $afterHeading = $imgSpec['insert_after_heading'] ?? '';
                if (empty($prompt)) continue;

                $uuid = $imageService->queue($post->id, $prompt, 'inline', $afterHeading, 'imagen-pro', '4:3');
                if ($uuid) $imageJobs[] = ['type' => 'inline', 'uuid' => $uuid];
            }

            $post->load(['category', 'translations']);

            return response()->json([
                'success' => true,
                'message' => 'Draft saved. Images generating in background.',
                'data' => [
                    'post_id' => $post->id,
                    'slug' => $post->slug,
                    'translations' => $post->translations->pluck('language')->toArray(),
                    'status' => 'draft',
                    'image_jobs' => $imageJobs,
                    'image_jobs_count' => count($imageJobs),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save draft article.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/automation/blog/image-webhook
     *
     * GeminiGen calls this when image generation completes or fails.
     * Public endpoint (no auth) — verified by GeminiGen signature.
     */
    public function imageWebhook(Request $request, ImageGenerationService $imageService): JsonResponse
    {
        $event = $request->input('event');
        $uuid = $request->input('uuid');
        $data = $request->input('data', []);

        Log::info("[ImageWebhook] Received: event={$event}, uuid={$uuid}");

        if (!$uuid || !$event) {
            return response()->json(['error' => 'Missing event or uuid'], 400);
        }

        $success = $imageService->handleWebhook($uuid, $event, $data);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Image processed' : 'Failed to process',
        ]);
    }

    /**
     * GET /api/automation/blog/image-status/{postId}
     *
     * Check image generation status for a post.
     */
    public function imageStatus(int $postId): JsonResponse
    {
        $jobs = ImageGenerationJob::where('post_id', $postId)
            ->select('id', 'uuid', 'type', 'status', 'image_url', 'error_message', 'created_at')
            ->get();

        $pending = $jobs->where('status', 'processing')->count();
        $completed = $jobs->where('status', 'completed')->count();
        $failed = $jobs->where('status', 'failed')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $postId,
                'total' => $jobs->count(),
                'pending' => $pending,
                'completed' => $completed,
                'failed' => $failed,
                'all_done' => $pending === 0,
                'jobs' => $jobs,
            ],
        ]);
    }
}
