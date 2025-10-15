<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * PostController
 *
 * Handles CRUD operations for blog posts with full i18n support
 */
class PostController extends Controller
{
    /**
     * Display a listing of posts.
     */
    public function index(Request $request): JsonResponse
    {
        $language = $request->query('lang', $request->header('Accept-Language', 'en'));
        $language = strtolower(substr($language, 0, 2));

        $query = Post::with(['category', 'translations'])
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->query('category'));
            });
        }

        // Filter by tag
        if ($request->has('tag')) {
            $tag = $request->query('tag');
            $query->whereJsonContains('tags', $tag);
        }

        // Filter by premium status
        if ($request->has('premium')) {
            $query->where('is_premium', (bool) $request->query('premium'));
        }

        // Search
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('excerpt', 'like', "%{$searchTerm}%")
                  ->orWhere('content', 'like', "%{$searchTerm}%")
                  ->orWhereHas('translations', function ($tq) use ($searchTerm) {
                      $tq->where('title', 'like', "%{$searchTerm}%")
                         ->orWhere('excerpt', 'like', "%{$searchTerm}%")
                         ->orWhere('content', 'like', "%{$searchTerm}%");
                  });
            });
        }

        $query->orderBy('published_at', 'desc');

        $perPage = min($request->query('per_page', 15), 50);
        $posts = $query->paginate($perPage);

        return response()->json([
            'data' => PostResource::collection($posts)->additional(['lang' => $language]),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
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
     * Display a listing of all posts for admin (including unpublished).
     */
    public function indexForAdmin(Request $request): JsonResponse
    {
        $query = Post::with(['category', 'translations']);

        // Filter by published status
        if ($request->has('published')) {
            $published = $request->query('published');
            if ($published === 'true' || $published === '1') {
                $query->where('published', true);
            } elseif ($published === 'false' || $published === '0') {
                $query->where('published', false);
            }
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        // Filter by premium status
        if ($request->has('is_premium')) {
            $query->where('is_premium', (bool) $request->query('is_premium'));
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

        $perPage = min($request->query('per_page', 10), 50);
        $posts = $query->paginate($perPage);

        \Log::info('[PostController] indexForAdmin called');
        \Log::info('[PostController] Total posts found: ' . $posts->total());
        \Log::info('[PostController] Posts items count: ' . $posts->count());
        \Log::info('[PostController] First post: ' . ($posts->first() ? $posts->first()->title : 'none'));

        return response()->json([
            'data' => PostResource::collection($posts),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
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
     * Display the specified post by slug.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $language = $request->query('lang', $request->header('Accept-Language', 'en'));
        $language = strtolower(substr($language, 0, 2));

        $post = Post::with(['category', 'translations'])
            ->where('slug', $slug)
            ->where('published', true)
            ->first();

        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'error' => 'The requested post does not exist or is not published.',
            ], 404);
        }

        // Increment views
        $post->incrementViews();

        return response()->json([
            'data' => (new PostResource($post))->additional(['lang' => $language]),
        ]);
    }

    /**
     * Display the specified post by ID (for admin).
     */
    public function showById(int $id): JsonResponse
    {
        $post = Post::with(['category', 'translations'])->find($id);

        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'error' => 'The requested post does not exist.',
            ], 404);
        }

        return response()->json([
            'message' => 'Post retrieved successfully',
            'data' => new PostResource($post),
        ]);
    }

    /**
     * Store a newly created post.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Prepare data (only Post model fields)
            $postData = $request->only([
                'category_id',
                'title',
                'slug',
                'excerpt',
                'content',
                'tags',
                'is_premium',
                'published',
                'published_at',
            ]);

            // Handle featured image (base64 or file)
            if ($request->filled('featured_image')) {
                $image = $request->input('featured_image');
                
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
                    $postData['featured_image'] = '/uploads/posts/' . $filename;
                } else {
                    // It's already a URL/path
                    $postData['featured_image'] = $image;
                }
            } elseif ($request->hasFile('featured_image')) {
                // Handle traditional file upload
                $file = $request->file('featured_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/posts'), $filename);
                $postData['featured_image'] = '/uploads/posts/' . $filename;
            }

            $postData['views'] = 0;

            $post = Post::create($postData);

            // Handle translations if provided
            foreach ($request->input('translations', []) as $translation) {
                $post->translations()->create($translation);
            }

            DB::commit();

            $post->load(['category', 'translations']);

            return response()->json([
                'message' => 'Post created successfully',
                'data' => new PostResource($post),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified post.
     */
    public function update(UpdatePostRequest $request, int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'error' => 'The requested post does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Prepare update data (only Post model fields)
            $updateData = $request->only([
                'category_id',
                'title',
                'slug',
                'excerpt',
                'content',
                'tags',
                'is_premium',
                'published',
                'published_at',
            ]);

            // Handle featured image (base64 or file)
            if ($request->filled('featured_image')) {
                $image = $request->input('featured_image');
                
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
                    $updateData['featured_image'] = '/uploads/posts/' . $filename;
                } else {
                    // It's already a URL/path
                    $updateData['featured_image'] = $image;
                }
            } elseif ($request->hasFile('featured_image')) {
                // Handle traditional file upload
                $file = $request->file('featured_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/posts'), $filename);
                $updateData['featured_image'] = '/uploads/posts/' . $filename;
            }

            $post->update($updateData);

            // Handle translations if provided
            if ($request->has('translations')) {
                foreach ($request->input('translations', []) as $translation) {
                    if (isset($translation['id'])) {
                        $post->translations()->where('id', $translation['id'])->update($translation);
                    } else {
                        $post->translations()->create($translation);
                    }
                }
            }

            DB::commit();

            $post->load(['category', 'translations']);

            return response()->json([
                'message' => 'Post updated successfully',
                'data' => new PostResource($post),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified post.
     */
    public function destroy(int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'error' => 'The requested post does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $post->translations()->delete();
            $post->delete();

            DB::commit();

            return response()->json([
                'message' => 'Post deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
