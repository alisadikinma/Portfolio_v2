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
use Illuminate\Support\Facades\Storage;
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

            // Ensure slug is unique (auto-increment suffix if duplicate)
            $originalSlug = $postData['slug'];
            $counter = 1;
            while (Post::where('slug', $postData['slug'])->exists()) {
                $postData['slug'] = $originalSlug . '-' . $counter;
                $counter++;
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
            
            // Handle SEO fields from request
            $seoFields = ['meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'og_image', 'canonical_url', 'ai_summary', 'schema_markup', 'faq_schema'];
            foreach ($seoFields as $field) {
                if ($request->filled($field)) {
                    $postData[$field] = $request->input($field);
                }
            }

            $post = Post::create($postData);

            // Auto-create default English translation for compatibility
            // This ensures posts work with both API and Admin panel
            $post->translations()->create([
                'language' => 'en',
                'title' => $postData['title'],
                'slug' => $postData['slug'],
                'excerpt' => $postData['excerpt'] ?? null,
                'content' => $postData['content'],
                'meta_title' => $postData['meta_title'] ?? $postData['title'],
                'meta_description' => $postData['meta_description'] ?? $postData['excerpt'],
                'meta_keywords' => $postData['meta_keywords'] ?? null,
                'og_title' => $postData['og_title'] ?? $postData['title'],
                'og_description' => $postData['og_description'] ?? $postData['excerpt'],
                'canonical_url' => $postData['canonical_url'] ?? null,
                'ai_summary' => $postData['ai_summary'] ?? null,
                'schema_markup' => $postData['schema_markup'] ?? null,
                'faq_schema' => $postData['faq_schema'] ?? null,
            ]);

            DB::commit();

            $post->load(['category', 'translations']);

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
            'posts.*.category_id' => 'required|exists:categories,id',
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
     * Upload multiple images via direct file upload (multipart/form-data)
     * Supports 1-20 images per request
     * 
     * Accepts multiple formats:
     * - images[] (array of files) - standard multipart
     * - images (single file) - will be converted to array
     * - image (single file) - alias for convenience
     * - Any file field name (for n8n compatibility)
     * - Base64 data in JSON body
     *
     * @param Request $request (multipart/form-data or JSON)
     * @return JsonResponse
     */
    public function uploadImages(Request $request): JsonResponse
    {
        $files = [];
        
        // Method 1: Check specific field names
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            $files = is_array($images) ? $images : [$images];
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        } elseif ($request->hasFile('file')) {
            $files = [$request->file('file')];
        } elseif ($request->hasFile('data')) {
            $dataFiles = $request->file('data');
            $files = is_array($dataFiles) ? $dataFiles : [$dataFiles];
        } else {
            // Method 2: Get ALL uploaded files (n8n sends with dynamic names)
            $allFiles = $request->allFiles();
            if (!empty($allFiles)) {
                foreach ($allFiles as $key => $fileOrArray) {
                    if (is_array($fileOrArray)) {
                        $files = array_merge($files, $fileOrArray);
                    } else {
                        $files[] = $fileOrArray;
                    }
                }
            }
        }

        // Method 3: Check for base64 data in JSON body
        if (empty($files)) {
            $jsonImages = $request->input('images');
            if (is_array($jsonImages)) {
                foreach ($jsonImages as $index => $imgData) {
                    // Support base64 format: { "data": "base64string", "filename": "name.jpg" }
                    // Or direct base64 string
                    $base64 = is_array($imgData) ? ($imgData['data'] ?? $imgData['base64'] ?? null) : $imgData;
                    
                    if ($base64 && preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
                        $imageData = substr($base64, strpos($base64, ',') + 1);
                        $imageContent = base64_decode($imageData);
                        $extension = $matches[1];
                        
                        if ($imageContent) {
                            $filename = 'content_' . time() . '_' . $index . '_' . Str::random(6) . '.' . $extension;
                            $path = 'images/' . $filename;
                            Storage::disk('public')->put($path, $imageContent);
                            
                            $files[] = (object)[
                                '_base64_processed' => true,
                                'url' => url('storage/' . $path),
                                'filename' => $filename,
                                'size' => strlen($imageContent),
                                'mime_type' => 'image/' . $extension
                            ];
                        }
                    }
                }
            }
        }

        // Debug: Log what we received
        \Log::info('[uploadImages] Request info', [
            'content_type' => $request->header('Content-Type'),
            'has_files' => $request->hasFile('images') || $request->hasFile('image'),
            'all_files_keys' => array_keys($request->allFiles()),
            'all_input_keys' => array_keys($request->all()),
            'files_count' => count($files)
        ]);

        // Validate we have files
        if (empty($files)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_FILES',
                    'message' => 'No image files provided.',
                    'hint' => 'Use multipart/form-data with field: images, image, file, or data. Or send JSON with base64 images array.',
                    'debug' => [
                        'content_type' => $request->header('Content-Type'),
                        'received_keys' => array_keys($request->all()),
                        'file_keys' => array_keys($request->allFiles())
                    ]
                ]
            ], 422);
        }

        // Validate max count
        if (count($files) > 20) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOO_MANY_FILES',
                    'message' => 'Maximum 20 images per request. Received: ' . count($files)
                ]
            ], 422);
        }

        $uploaded = [];
        $failed = [];

        foreach ($files as $index => $file) {
            try {
                // Skip null entries
                if (!$file) {
                    continue;
                }

                // Check if already processed (base64)
                if (is_object($file) && isset($file->_base64_processed)) {
                    $uploaded[] = [
                        'index' => $index,
                        'url' => $file->url,
                        'filename' => $file->filename,
                        'size' => $file->size,
                        'mime_type' => $file->mime_type
                    ];
                    continue;
                }

                // Validate file is valid UploadedFile
                if (!($file instanceof \Illuminate\Http\UploadedFile)) {
                    $failed[] = [
                        'index' => $index,
                        'error' => 'Invalid file object type: ' . gettype($file)
                    ];
                    continue;
                }

                if (!$file->isValid()) {
                    $failed[] = [
                        'index' => $index,
                        'original_name' => $file->getClientOriginalName() ?? 'unknown',
                        'error' => 'Invalid file upload: ' . $file->getErrorMessage()
                    ];
                    continue;
                }

                // Validate mime type
                $mimeType = $file->getMimeType();
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($mimeType, $allowedTypes)) {
                    $failed[] = [
                        'index' => $index,
                        'original_name' => $file->getClientOriginalName(),
                        'error' => "Invalid image type: {$mimeType}. Allowed: jpeg, png, gif, webp"
                    ];
                    continue;
                }

                // Validate file size (max 10MB)
                if ($file->getSize() > 10 * 1024 * 1024) {
                    $failed[] = [
                        'index' => $index,
                        'original_name' => $file->getClientOriginalName(),
                        'error' => 'File too large. Max 10MB'
                    ];
                    continue;
                }
                
                // Generate extension from mime type
                $extension = match($mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    default => $file->getClientOriginalExtension()
                };

                // Generate unique filename with index to maintain order
                $filename = 'content_' . time() . '_' . $index . '_' . Str::random(6) . '.' . $extension;
                $path = 'images/' . $filename;

                // Save to storage/app/public/images/
                Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

                $fullUrl = url('storage/' . $path);

                $uploaded[] = [
                    'index' => $index,
                    'url' => $fullUrl,
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $mimeType
                ];

            } catch (\Exception $e) {
                $failed[] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Log batch upload untuk audit
        $this->logAutomationRequest($request, 'images.batch_upload', [
            'total_requested' => count($files),
            'uploaded' => count($uploaded),
            'failed' => count($failed)
        ]);

        // 207 Multi-Status jika ada yang failed
        $statusCode = count($failed) > 0 ? 207 : 200;

        return response()->json([
            'success' => count($uploaded) > 0,
            'data' => [
                'uploaded' => $uploaded,
                'failed' => $failed
            ],
            'summary' => [
                'total' => count($files),
                'uploaded' => count($uploaded),
                'failed' => count($failed)
            ],
            'message' => sprintf(
                'Batch upload completed: %d uploaded, %d failed',
                count($uploaded),
                count($failed)
            )
        ], $statusCode);
    }

    /**
     * Upload single image via direct file upload
     *
     * @param Request $request (multipart/form-data)
     * Field: image - single image file
     * @return JsonResponse
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|mimes:jpeg,jpg,png,gif,webp|max:10240'
        ]);

        try {
            $file = $request->file('image');

            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UPLOAD_FAILED',
                        'message' => 'Invalid file upload: ' . $file->getErrorMessage()
                    ]
                ], 400);
            }

            // Get mime type
            $mimeType = $file->getMimeType();
            
            // Generate extension from mime type
            $extension = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => $file->getClientOriginalExtension()
            };

            // Generate unique filename
            $filename = 'content_' . time() . '_' . Str::random(8) . '.' . $extension;
            $path = 'images/' . $filename;

            // Save to storage/app/public/images/
            Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

            $fullUrl = url('storage/' . $path);

            // Log for audit
            $this->logAutomationRequest($request, 'images.single_upload', [
                'filename' => $filename,
                'size' => $file->getSize()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $fullUrl,
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $mimeType
                ],
                'message' => 'Image uploaded successfully'
            ]);

        } catch (\Exception $e) {
            $this->logAutomationRequest($request, 'images.single_upload.failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPLOAD_FAILED',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
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
