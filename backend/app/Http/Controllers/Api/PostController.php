<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
     * Store a newly created post.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:posts,slug'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'is_premium' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],

            // Translations
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.language' => ['required', 'string', Rule::in(['en', 'id'])],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['required', 'string', 'max:255'],
            'translations.*.excerpt' => ['nullable', 'string'],
            'translations.*.content' => ['required', 'string'],
            'translations.*.meta_title' => ['nullable', 'string', 'max:255'],
            'translations.*.meta_description' => ['nullable', 'string', 'max:500'],
            'translations.*.og_title' => ['nullable', 'string', 'max:255'],
            'translations.*.og_description' => ['nullable', 'string', 'max:500'],
            'translations.*.canonical_url' => ['nullable', 'url', 'max:255'],
            'translations.*.ai_summary' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $post = Post::create([
                'category_id' => $request->input('category_id'),
                'title' => $request->input('title'),
                'slug' => $request->input('slug'),
                'excerpt' => $request->input('excerpt'),
                'content' => $request->input('content'),
                'featured_image' => $request->input('featured_image'),
                'tags' => $request->input('tags'),
                'is_premium' => $request->input('is_premium', false),
                'published' => $request->input('published', false),
                'published_at' => $request->input('published_at'),
                'views' => 0,
            ]);

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
    public function update(Request $request, int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'error' => 'The requested post does not exist.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($id)],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'is_premium' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],

            // Translations
            'translations' => ['nullable', 'array'],
            'translations.*.id' => ['nullable', 'integer', 'exists:post_translations,id'],
            'translations.*.language' => ['required', 'string', Rule::in(['en', 'id'])],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['required', 'string', 'max:255'],
            'translations.*.excerpt' => ['nullable', 'string'],
            'translations.*.content' => ['required', 'string'],
            'translations.*.meta_title' => ['nullable', 'string', 'max:255'],
            'translations.*.meta_description' => ['nullable', 'string', 'max:500'],
            'translations.*.og_title' => ['nullable', 'string', 'max:255'],
            'translations.*.og_description' => ['nullable', 'string', 'max:500'],
            'translations.*.canonical_url' => ['nullable', 'url', 'max:255'],
            'translations.*.ai_summary' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $post->update($request->only([
                'category_id',
                'title',
                'slug',
                'excerpt',
                'content',
                'featured_image',
                'tags',
                'is_premium',
                'published',
                'published_at',
            ]));

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
