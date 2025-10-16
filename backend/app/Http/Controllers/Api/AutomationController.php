<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AutomationPostRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\CategoryResource;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AutomationController
 *
 * Dedicated endpoints for automation platforms (n8n, Zapier, Make.com)
 * Features: simplified validation, bulk operations, webhooks
 */
class AutomationController extends Controller
{
    /**
     * Get posts with advanced filters for automation
     */
    public function getPosts(Request $request): JsonResponse
    {
        $query = Post::with(['category']);

        // Filter by published status
        if ($request->has('published')) {
            $query->where('published', filter_var($request->query('published'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        // Filter by premium status
        if ($request->has('is_premium')) {
            $query->where('is_premium', filter_var($request->query('is_premium'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        // Search
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('excerpt', 'like', "%{$searchTerm}%")
                  ->orWhere('content', 'like', "%{$searchTerm}%");
            });
        }

        // Sort
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($request->query('per_page', 25), 100);
        $posts = $query->paginate($perPage);

        // Log request for audit trail
        $this->logAutomationRequest($request, 'posts.index', [
            'filters' => $request->all(),
            'results_count' => $posts->count()
        ]);

        return response()->json([
            'success' => true,
            'data' => PostResource::collection($posts),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
                'processed_at' => now()->toIso8601String(),
            ],
            'links' => [
                'first' => $posts->url(1),
                'last' => $posts->url($posts->lastPage()),
                'prev' => $posts->previousPageUrl(),
                'next' => $posts->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get single post by ID for automation
     */
    public function getPost(int $id): JsonResponse
    {
        $post = Post::with(['category'])->find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POST_NOT_FOUND',
                    'message' => 'The requested post does not exist.',
                ],
            ], 404);
        }

        $this->logAutomationRequest(request(), 'posts.show', ['post_id' => $id]);

        return response()->json([
            'success' => true,
            'data' => new PostResource($post),
            'meta' => [
                'processed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create post (simplified for automation)
     */
    public function createPost(AutomationPostRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $postData = $request->validated();

            // Auto-generate slug if not provided
            if (empty($postData['slug'])) {
                $postData['slug'] = Str::slug($postData['title']);
            }

            // Auto-generate excerpt if not provided
            if (empty($postData['excerpt']) && !empty($postData['content'])) {
                $postData['excerpt'] = Str::limit(strip_tags($postData['content']), 150);
            }

            // Auto-set published_at if published and not provided
            if (!empty($postData['published']) && empty($postData['published_at'])) {
                $postData['published_at'] = now();
            }

            // Handle featured_image (supports URL or base64)
            if (!empty($postData['featured_image'])) {
                $postData['featured_image'] = $this->handleFeaturedImage($postData['featured_image']);
            }

            // Set default views
            $postData['views'] = 0;

            $post = Post::create($postData);

            DB::commit();

            $post->load(['category']);

            $this->logAutomationRequest($request, 'posts.create', [
                'post_id' => $post->id,
                'title' => $post->title
            ]);

            return response()->json([
                'success' => true,
                'data' => new PostResource($post),
                'message' => 'Post created successfully',
                'meta' => [
                    'processed_at' => now()->toIso8601String(),
                    'word_count' => str_word_count(strip_tags($post->content)),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logAutomationRequest($request, 'posts.create.failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POST_CREATION_FAILED',
                    'message' => 'Failed to create post',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Update post
     */
    public function updatePost(AutomationPostRequest $request, int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POST_NOT_FOUND',
                    'message' => 'The requested post does not exist.',
                ],
            ], 404);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->validated();

            // Handle featured_image (supports URL or base64)
            if (!empty($updateData['featured_image'])) {
                $updateData['featured_image'] = $this->handleFeaturedImage($updateData['featured_image']);
            }

            $post->update($updateData);

            DB::commit();

            $post->load(['category']);

            $this->logAutomationRequest($request, 'posts.update', [
                'post_id' => $id,
                'title' => $post->title
            ]);

            return response()->json([
                'success' => true,
                'data' => new PostResource($post),
                'message' => 'Post updated successfully',
                'meta' => [
                    'processed_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logAutomationRequest($request, 'posts.update.failed', [
                'post_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POST_UPDATE_FAILED',
                    'message' => 'Failed to update post',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Delete post
     */
    public function deletePost(int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POST_NOT_FOUND',
                    'message' => 'The requested post does not exist.',
                ],
            ], 404);
        }

        try {
            DB::beginTransaction();

            $title = $post->title;
            $post->delete();

            DB::commit();

            $this->logAutomationRequest(request(), 'posts.delete', [
                'post_id' => $id,
                'title' => $title
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully',
                'meta' => [
                    'processed_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logAutomationRequest(request(), 'posts.delete.failed', [
                'post_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POST_DELETION_FAILED',
                    'message' => 'Failed to delete post',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Bulk create posts (up to 50 at once)
     */
    public function bulkCreatePosts(Request $request): JsonResponse
    {
        $request->validate([
            'posts' => 'required|array|max:50',
            'posts.*.title' => 'required|string|max:255',
            'posts.*.content' => 'required|string',
            'posts.*.category_id' => 'required|exists:blog_categories,id',
        ]);

        $posts = $request->input('posts');
        $created = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($posts as $index => $postData) {
                try {
                    // Auto-generate slug if not provided
                    if (empty($postData['slug'])) {
                        $postData['slug'] = Str::slug($postData['title']);
                    }

                    // Auto-generate excerpt if not provided
                    if (empty($postData['excerpt']) && !empty($postData['content'])) {
                        $postData['excerpt'] = Str::limit(strip_tags($postData['content']), 150);
                    }

                    // Auto-set published_at if published and not provided
                    if (!empty($postData['published']) && empty($postData['published_at'])) {
                        $postData['published_at'] = now();
                    }

                    // Handle featured_image if present
                    if (!empty($postData['featured_image'])) {
                        $postData['featured_image'] = $this->handleFeaturedImage($postData['featured_image']);
                    }

                    $postData['views'] = 0;

                    $post = Post::create($postData);
                    $post->load(['category']);

                    $created[] = [
                        'index' => $index,
                        'id' => $post->id,
                        'title' => $post->title,
                        'slug' => $post->slug,
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'title' => $postData['title'] ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            $this->logAutomationRequest($request, 'posts.bulk_create', [
                'requested' => count($posts),
                'created' => count($created),
                'failed' => count($errors)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'created' => $created,
                    'errors' => $errors,
                ],
                'message' => sprintf('Bulk operation completed: %d created, %d failed', count($created), count($errors)),
                'meta' => [
                    'total_requested' => count($posts),
                    'total_created' => count($created),
                    'total_failed' => count($errors),
                    'processed_at' => now()->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logAutomationRequest($request, 'posts.bulk_create.failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BULK_CREATE_FAILED',
                    'message' => 'Bulk creation failed',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get categories for automation
     */
    public function getCategories(Request $request): JsonResponse
    {
        $categories = Category::orderBy('name', 'asc')->get();

        $this->logAutomationRequest($request, 'categories.index', [
            'count' => $categories->count()
        ]);

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'total' => $categories->count(),
                'processed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Webhook endpoint for post published event
     */
    public function postPublishedWebhook(Request $request): JsonResponse
    {
        // This endpoint is called internally when a post is published
        // External automation platforms can register webhooks here

        $postId = $request->input('post_id');
        $post = Post::with(['category'])->find($postId);

        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POST_NOT_FOUND',
                    'message' => 'Post not found',
                ],
            ], 404);
        }

        $this->logAutomationRequest($request, 'webhook.post_published', [
            'post_id' => $postId,
            'title' => $post->title
        ]);

        return response()->json([
            'success' => true,
            'event' => 'post.published',
            'data' => new PostResource($post),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Handle featured image (URL or base64)
     */
    private function handleFeaturedImage(string $image): string
    {
        // Check if it's base64 data
        if (preg_match('/^data:image\/(\w+);base64,/', $image, $matches)) {
            $imageData = substr($image, strpos($image, ',') + 1);
            $imageData = base64_decode($imageData);
            $extension = $matches[1];
            $filename = time() . '_' . uniqid() . '.' . $extension;

            $uploadDir = public_path('uploads/posts');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            file_put_contents($uploadDir . '/' . $filename, $imageData);
            return '/uploads/posts/' . $filename;
        }

        // It's already a URL/path
        return $image;
    }

    /**
     * Log automation request for audit trail
     */
    private function logAutomationRequest(Request $request, string $action, array $metadata = []): void
    {
        try {
            DB::table('automation_logs')->insert([
                'user_id' => $request->user()->id ?? null,
                'token_id' => $request->user()->currentAccessToken()->id ?? null,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => json_encode($metadata),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail if logging fails
            \Log::error('Failed to log automation request: ' . $e->getMessage());
        }
    }
}
