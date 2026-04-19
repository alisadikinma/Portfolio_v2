<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AwardController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\TestimonialController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\SitemapController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\PageSectionController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\GalleryItemController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\ActivityFeedController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\CarouselDraftController;
use App\Http\Controllers\Api\Admin\ContentIdeaController;

// ============================================
// Authentication Routes
// ============================================

// Public Authentication Routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Protected Authentication Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ============================================
// Public API Routes
// ============================================

// Public Awards Routes
Route::prefix('awards')->group(function () {
    Route::get('/', [AwardController::class, 'index']);
    Route::get('/{id}', [AwardController::class, 'show']);
    Route::get('/{id}/galleries', [AwardController::class, 'getGalleries']);
});

// Public Projects Routes
Route::prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index']);
    Route::get('/{slug}', [ProjectController::class, 'show']);
});

// Public Posts Routes
Route::prefix('posts')->group(function () {
    Route::get('/', [PostController::class, 'index']);
    Route::post('/check-duplicate', [PostController::class, 'checkDuplicate']); // For n8n/automation workflows
    Route::get('/{slug}', [PostController::class, 'show']);
});

// Public Categories Routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{slug}', [CategoryController::class, 'show']);
});

// Public Gallery Routes
Route::prefix('galleries')->group(function () {
    Route::get('/', [GalleryController::class, 'index']);
    Route::get('/{id}', [GalleryController::class, 'show']);
    Route::get('/{galleryId}/items', [GalleryItemController::class, 'index']);
});

// Gallery route alias (singular)
Route::prefix('gallery')->group(function () {
    Route::get('/', [GalleryController::class, 'index']);
    Route::get('/{id}', [GalleryController::class, 'show']);
    Route::get('/{galleryId}/items', [GalleryItemController::class, 'index']);
});

// Public Contact Route (Rate Limited: 3 requests per 15 minutes per IP)
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:3,15');

// Public Testimonials Routes
Route::prefix('testimonials')->group(function () {
    Route::get('/', [TestimonialController::class, 'index']);
    Route::get('/{id}', [TestimonialController::class, 'show']);
});

// Public Services Routes
Route::prefix('services')->group(function () {
    Route::get('/', [ServiceController::class, 'index']);
    Route::get('/{slug}', [ServiceController::class, 'show']);
});

// Public Settings Routes
Route::prefix('settings')->group(function () {
    Route::get('/', [SettingController::class, 'index']);
    Route::get('/about', [SettingsController::class, 'getAboutSettings']); // NEW: Public about page data
    Route::get('/site', [SettingsController::class, 'getSiteSettings']); // NEW: Public site settings
    Route::get('/{group}', [SettingController::class, 'getByGroup']);
});

// SEO Sitemap Routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-index.xml', [SitemapController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts'])->name('sitemap.posts');
Route::get('/sitemap-projects.xml', [SitemapController::class, 'projects'])->name('sitemap.projects');

// Health check
Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));

// Chatbot
Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])->middleware('throttle:10,1');

// GEO — Machine-readable portfolio for AI crawlers
Route::get('/llms.txt', [GeoController::class, 'llmsTxt']);
Route::get('/llms-full.txt', [GeoController::class, 'llmsFullTxt']);

// Activity Feed
Route::get('/activity-feed', [ActivityFeedController::class, 'index']);

// Newsletter
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:5,60');
Route::delete('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);

// Public Menu Items Routes (for navbar)
Route::get('/menu-items', [MenuItemController::class, 'publicMenuItems']);

// Public Page Sections Routes (for page rendering)
Route::get('/page-sections', [PageSectionController::class, 'publicSections']);

// ============================================
// Admin API Routes (Protected)
// ============================================

// Admin Dashboard Routes
Route::middleware(['auth:sanctum'])->prefix('admin/dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats']);
});

// Admin Awards Routes
Route::middleware(['auth:sanctum'])->prefix('admin/awards')->group(function () {
    Route::get('/', [AwardController::class, 'indexForAdmin']);
    Route::get('/{id}', [AwardController::class, 'show']);
    Route::post('/', [AwardController::class, 'store']);
    Route::put('/{id}', [AwardController::class, 'update']);
    Route::delete('/{id}', [AwardController::class, 'destroy']);
    Route::post('/{id}/galleries', [AwardController::class, 'linkGallery']);
    Route::delete('/{id}/galleries/{galleryId}', [AwardController::class, 'unlinkGallery']);
    Route::put('/{id}/galleries/reorder', [AwardController::class, 'reorderGalleries']);
});

// Admin Projects Routes
Route::middleware(['auth:sanctum'])->prefix('admin/projects')->group(function () {
    Route::get('/', [ProjectController::class, 'indexForAdmin']);
    Route::get('/{id}', [ProjectController::class, 'showById']);
    Route::post('/', [ProjectController::class, 'store']);
    Route::put('/{id}', [ProjectController::class, 'update']);
    Route::delete('/{id}', [ProjectController::class, 'destroy']);
});

// Admin Posts Routes
Route::middleware(['auth:sanctum'])->prefix('admin/posts')->group(function () {
    Route::get('/', [PostController::class, 'indexForAdmin']);
    Route::get('/{id}', [PostController::class, 'showById']);
    Route::post('/', [PostController::class, 'store']);
    Route::put('/{id}', [PostController::class, 'update']);
    Route::delete('/{id}', [PostController::class, 'destroy']);
});

// Admin Categories Routes
Route::middleware(['auth:sanctum'])->prefix('admin/categories')->group(function () {
    Route::get('/', [CategoryController::class, 'indexForAdmin']);
    Route::get('/{id}', [CategoryController::class, 'showById']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::put('/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
});

// Admin Gallery Routes
Route::middleware(['auth:sanctum'])->prefix('admin/galleries')->group(function () {
    Route::get('/', [GalleryController::class, 'index']);
    Route::post('/bulk-upload', [GalleryController::class, 'bulkUpload']); // MUST be before /{id}
    Route::get('/{id}', [GalleryController::class, 'show']);
    Route::post('/', [GalleryController::class, 'store']);
    Route::put('/{id}', [GalleryController::class, 'update']);
    Route::delete('/{id}', [GalleryController::class, 'destroy']);

    // Gallery Items Routes (nested resource)
    Route::get('/{galleryId}/items', [GalleryItemController::class, 'index']);
    Route::post('/{galleryId}/items', [GalleryItemController::class, 'store']);
    Route::post('/{galleryId}/items/bulk-upload', [GalleryItemController::class, 'bulkUpload']);
    Route::get('/{galleryId}/items/{id}', [GalleryItemController::class, 'show']);
    Route::put('/{galleryId}/items/{id}', [GalleryItemController::class, 'update']);
    Route::delete('/{galleryId}/items/{id}', [GalleryItemController::class, 'destroy']);
});

// Admin Gallery Routes (singular alias)
Route::middleware(['auth:sanctum'])->prefix('admin/gallery')->group(function () {
    Route::get('/', [GalleryController::class, 'index']);
    Route::post('/bulk-upload', [GalleryController::class, 'bulkUpload']); // MUST be before /{id}
    Route::get('/{id}', [GalleryController::class, 'show']);
    Route::post('/', [GalleryController::class, 'store']);
    Route::put('/{id}', [GalleryController::class, 'update']);
    Route::delete('/{id}', [GalleryController::class, 'destroy']);

    // Gallery Items Routes (nested resource)
    Route::get('/{galleryId}/items', [GalleryItemController::class, 'index']);
    Route::post('/{galleryId}/items', [GalleryItemController::class, 'store']);
    Route::post('/{galleryId}/items/bulk-upload', [GalleryItemController::class, 'bulkUpload']);
    Route::get('/{galleryId}/items/{id}', [GalleryItemController::class, 'show']);
    Route::put('/{galleryId}/items/{id}', [GalleryItemController::class, 'update']);
    Route::delete('/{galleryId}/items/{id}', [GalleryItemController::class, 'destroy']);
});

// Admin Testimonials Routes
Route::middleware(['auth:sanctum'])->prefix('admin/testimonials')->group(function () {
    Route::get('/', [TestimonialController::class, 'indexForAdmin']);
    Route::get('/{id}', [TestimonialController::class, 'show']);
    Route::post('/', [TestimonialController::class, 'store']);
    Route::put('/{id}', [TestimonialController::class, 'update']);
    Route::delete('/{id}', [TestimonialController::class, 'destroy']);
});

// Admin Services Routes
Route::middleware(['auth:sanctum'])->prefix('admin/services')->group(function () {
    Route::get('/', [ServiceController::class, 'index']);
    Route::get('/{slug}', [ServiceController::class, 'show']);
    Route::post('/', [ServiceController::class, 'store']);
    Route::put('/{slug}', [ServiceController::class, 'update']);
    Route::delete('/{slug}', [ServiceController::class, 'destroy']);
});

// Admin Contact Routes
Route::middleware(['auth:sanctum'])->prefix('admin/contacts')->group(function () {
    Route::get('/', [ContactController::class, 'index']);
    Route::get('/export', [ContactController::class, 'export']);
    Route::get('/{id}', [ContactController::class, 'show']);
    Route::patch('/{id}/mark-as-read', [ContactController::class, 'markAsRead']);
    Route::delete('/{id}', [ContactController::class, 'destroy']);
});

// Admin Settings Routes
Route::middleware(['auth:sanctum'])->prefix('admin/settings')->group(function () {
    Route::get('/about', [SettingsController::class, 'getAboutSettings']);
    Route::put('/about', [SettingsController::class, 'updateAboutSettings']);
    Route::post('/about', [SettingsController::class, 'updateAboutSettings']); // POST with _method=PUT for FormData
    Route::get('/site', [SettingsController::class, 'getSiteSettings']);
    Route::put('/site', [SettingsController::class, 'updateSiteSettings']);
    Route::post('/site', [SettingsController::class, 'updateSiteSettings']); // POST with _method=PUT for FormData
    Route::get('/creator-brand', [SettingsController::class, 'getCreatorBrandSettings']);
    Route::put('/creator-brand', [SettingsController::class, 'updateCreatorBrandSettings']);
    Route::post('/creator-brand', [SettingsController::class, 'updateCreatorBrandSettings']); // POST with _method=PUT for FormData
});

// Admin Menu Items Routes
Route::middleware(['auth:sanctum'])->prefix('admin/menu-items')->group(function () {
    Route::get('/', [MenuItemController::class, 'index']);
    Route::post('/', [MenuItemController::class, 'store']);
    // IMPORTANT: Specific routes must come BEFORE generic {id} routes
    Route::put('/reorder', [MenuItemController::class, 'reorder']);
    Route::put('/{id}', [MenuItemController::class, 'update']);
    Route::delete('/{id}', [MenuItemController::class, 'destroy']);
});

// Admin Page Sections Routes
Route::middleware(['auth:sanctum'])->prefix('admin/page-sections')->group(function () {
    Route::get('/', [PageSectionController::class, 'index']);
    // IMPORTANT: Specific routes must come BEFORE generic {id} routes
    Route::put('/reorder', [PageSectionController::class, 'reorder']);
    Route::put('/{id}', [PageSectionController::class, 'update']);
});

// ============================================
// Automation API Routes (n8n, Zapier, Make.com)
// ============================================

// Public automation routes (no auth required)
Route::prefix('automation')->group(function () {
    // Check duplicate post (public, no auth needed)
    Route::post('/posts/check-duplicate', [PostController::class, 'checkDuplicate']);

    // GeminiGen image webhook (public, no auth — called by GeminiGen servers)
    Route::post('/blog/image-webhook', [\App\Http\Controllers\Api\BlogPipelineController::class, 'imageWebhook']);

    // Carousel webhook (Content Engine calls this to save carousel draft)
    Route::post('/carousel/save-draft', [CarouselDraftController::class, 'saveDraft']);
});

// Protected automation routes (require auth token)
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('automation')->group(function () {
    // Image uploads for blog content
    Route::post('/upload-images', [AutomationController::class, 'uploadImages']); // Batch (recommended)
    Route::post('/upload-image', [AutomationController::class, 'uploadImage']);   // Single (fallback)

    // Posts endpoints
    Route::get('/posts', [AutomationController::class, 'getPosts']);
    Route::get('/posts/{id}', [AutomationController::class, 'getPost']);
    Route::post('/posts', [AutomationController::class, 'createPost']);
    Route::put('/posts/{id}', [AutomationController::class, 'updatePost']);
    Route::delete('/posts/{id}', [AutomationController::class, 'deletePost']);
    Route::post('/posts/bulk', [AutomationController::class, 'bulkCreatePosts']);

    // Categories endpoint
    Route::get('/categories', [AutomationController::class, 'getCategories']);

    // Webhook endpoint
    Route::post('/webhook/published', [AutomationController::class, 'postPublishedWebhook']);

    // Blog Pipeline (Claude Scheduled Task integration)
    Route::get('/blog/trending-topic', [\App\Http\Controllers\Api\BlogPipelineController::class, 'trendingTopic']);
    Route::post('/blog/save-draft', [\App\Http\Controllers\Api\BlogPipelineController::class, 'saveDraft']);

    // Content Ideas Pipeline (for Claude CLI article-content-writer plugin)
    Route::get('/content-ideas/pending', function () {
        $idea = \App\Models\ContentIdea::where('status', 'researching')
            ->orderBy('updated_at', 'asc')
            ->first();

        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'No pending ideas.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $idea,
        ]);
    });

    // Progress callback — called by CLI plugin at each step
    Route::put('/content-ideas/{id}/progress', function (\Illuminate\Http\Request $request, $id) {
        $idea = \App\Models\ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Idea not found.'], 404);
        }

        $request->validate([
            'step' => 'required|string|max:50',
            'percentage' => 'required|integer|min:0|max:100',
            'message' => 'required|string|max:500',
        ]);

        $logEntry = [
            'timestamp' => now()->toISOString(),
            'step' => $request->input('step'),
            'percentage' => $request->input('percentage'),
            'message' => $request->input('message'),
        ];

        $updateData = [
            'progress_percentage' => $request->input('percentage'),
            'current_step' => $request->input('step'),
            'progress_log' => array_merge($idea->progress_log ?? [], [$logEntry]),
        ];

        // Handle brand manifest from article-images skill
        if ($request->input('step') === 'manifest_needed' && $request->has('manifest')) {
            $article = $idea->generated_article ?? [];
            $article['brand_manifest'] = $request->input('manifest');
            $updateData['generated_article'] = $article;

            // In automation mode (auto_mode), skip blocking — all items optional
            if (!$idea->auto_mode) {
                $updateData['status'] = 'awaiting_brand_images';
            }
        }

        $idea->update($updateData);

        return response()->json(['success' => true, 'data' => $logEntry]);
    });

    // Completion callback — called by CLI plugin when article is done
    // Accepts BOTH old format (generated_article) and new format (article + seo_analysis + ...)
    Route::put('/content-ideas/{id}/complete', function (\Illuminate\Http\Request $request, $id) {
        $idea = \App\Models\ContentIdea::findOrFail($id);

        \Illuminate\Support\Facades\Log::info('[ContentIdea] /complete called', [
            'idea_id' => $id,
            'has_article' => $request->has('article'),
            'has_generated_article' => $request->has('generated_article'),
            'article_keys' => $request->has('article') ? array_keys($request->input('article', [])) : [],
            'article_title' => $request->input('article.title', 'MISSING'),
            'article_content_len' => strlen($request->input('article.content', '')),
            'top_level_keys' => array_keys($request->all()),
            'content_length' => $request->header('Content-Length'),
        ]);

        // Defense: reject shell-quoting corruption where body parses as
        // {"<url>": null} instead of a real structured payload.
        if (!$request->has('article') && !$request->has('generated_article') && !$request->has('combined_score')) {
            \Illuminate\Support\Facades\Log::warning('[complete] rejected malformed body', [
                'idea_id' => $id,
                'keys_received' => array_keys($request->all()),
                'raw_body_preview' => substr($request->getContent(), 0, 300),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Missing required fields. Body must include "article" or "combined_score". Use file-based curl to avoid shell quoting corruption.',
                'keys_received' => array_keys($request->all()),
            ], 400);
        }

        // Detect schema: new plugin sends "article" key, old sends "generated_article"
        if ($request->has('article')) {
            // New schema from article-content-writer plugin v1.1+
            $pluginArticle = $request->input('article', []);
            $languages = $idea->languages ?? ['en'];
            $existing = $idea->generated_article ?? [];

            // Build nested-by-language generated_article, preserving existing content
            // if the completion callback didn't include article body (e.g., score-only callback)
            $generatedArticle = [];
            foreach ($languages as $lang) {
                $existingLang = $existing[$lang] ?? [];
                $existingFlat = [
                    'title' => $existing['title'] ?? '',
                    'content' => $existing['content'] ?? '',
                    'word_count' => $existing['word_count'] ?? 0,
                ];

                $generatedArticle[$lang] = [
                    'title' => $pluginArticle['title']
                        ?? (is_array($existingLang) ? ($existingLang['title'] ?? null) : null)
                        ?? $existingFlat['title']
                        ?? $idea->title,
                    'content' => $pluginArticle['content']
                        ?? (is_array($existingLang) ? ($existingLang['content'] ?? null) : null)
                        ?? $existingFlat['content']
                        ?? '',
                    'word_count' => $pluginArticle['word_count']
                        ?? (is_array($existingLang) ? ($existingLang['word_count'] ?? null) : null)
                        ?? $existingFlat['word_count']
                        ?? 0,
                ];
            }

            // Shared metadata (prefer incoming, fall back to existing)
            $generatedArticle['target_keyword'] = $pluginArticle['keyword'] ?? $existing['target_keyword'] ?? $existing['keyword'] ?? '';
            $generatedArticle['framework'] = $pluginArticle['framework'] ?? $existing['framework'] ?? '';
            $generatedArticle['hook_type'] = $pluginArticle['hook_type'] ?? $existing['hook_type'] ?? '';
            $generatedArticle['hook_boost'] = $pluginArticle['hook_boost'] ?? $existing['hook_boost'] ?? '';
            $generatedArticle['emotional_arc'] = $pluginArticle['emotional_arc'] ?? $existing['emotional_arc'] ?? '';
            $generatedArticle['citation_count'] = $pluginArticle['citation_count'] ?? $existing['citation_count'] ?? 0;
            $generatedArticle['image_count'] = $pluginArticle['image_count'] ?? $existing['image_count'] ?? 0;

            // Scoring gates
            $generatedArticle['seo_analysis'] = $request->input('seo_analysis');
            $generatedArticle['quality_gate'] = $request->input('quality_gate');
            $generatedArticle['virality_score'] = $request->input('virality_score');

            // AI Humanization + GEO scores (from article-score)
            $generatedArticle['ai_humanization'] = $request->input('ai_humanization');
            $generatedArticle['geo_score'] = $request->input('geo_score');
            $generatedArticle['combined_score'] = $request->input('combined_score');

            // Backward-compat: flatten scores for quick access
            $generatedArticle['quality_score'] = $request->input('quality_gate.score', 0);
            $generatedArticle['virality_score_value'] = $request->input('virality_score.score', 0);
            $generatedArticle['seo_score'] = $request->input('seo_analysis.score', 0);

            // Image prompts (prefer incoming, fall back to existing from save-article)
            $generatedArticle['image_prompts'] = $request->input('image_prompts', $existing['image_prompts'] ?? []);

            // Sources from research
            $generatedArticle['sources'] = $request->input('research_data.sources', $existing['sources'] ?? []);

            // Preserve prep_data if it existed
            if (isset($existing['prep_data'])) {
                $generatedArticle['prep_data'] = $existing['prep_data'];
            }

            $idea->update([
                'status' => 'article_ready',
                'generated_article' => $generatedArticle,
                'research_data' => $request->input('research_data'),
                'progress_percentage' => 100,
                'current_step' => 'completed',
                'progress_log' => array_merge($idea->progress_log ?? [], [[
                    'timestamp' => now()->toISOString(),
                    'step' => 'completed',
                    'percentage' => 100,
                    'message' => 'Article generation completed successfully',
                ]]),
            ]);
        } else {
            // Old schema: generated_article passed directly
            $idea->update([
                'status' => 'article_ready',
                'generated_article' => $request->input('generated_article'),
                'research_data' => $request->input('research_data'),
                'progress_percentage' => 100,
                'current_step' => 'completed',
                'progress_log' => array_merge($idea->progress_log ?? [], [[
                    'timestamp' => now()->toISOString(),
                    'step' => 'completed',
                    'percentage' => 100,
                    'message' => 'Article generation completed successfully',
                ]]),
            ]);
        }

        // Auto mode: if enabled, auto-start image generation
        $idea->refresh();
        if ($idea->auto_mode && $idea->status === 'article_ready') {
            $imageService = app(\App\Services\ImageGenerationService::class);
            $article = $idea->generated_article ?? [];
            $prompts = $article['image_prompts'] ?? [];

            foreach ($prompts as $i => $prompt) {
                $uuid = $imageService->queue(
                    postId: null,
                    prompt: $prompt['visual_direction'] ?? $prompt['prompt'] ?? '',
                    type: ($prompt['type'] ?? 'inline') === 'cover' ? 'hero' : 'inline',
                    insertAfterHeading: null,
                    model: $prompt['model'] ?? config('content.default_image_model', 'nano-banana-pro'),
                    aspectRatio: $prompt['aspect_ratio'] ?? '16:9',
                    style: $prompt['style'] ?? 'Photorealistic'
                );
                if ($uuid) {
                    $prompts[$i]['job_uuid'] = $uuid;
                    $prompts[$i]['status'] = 'generating';
                }
            }

            $article['image_prompts'] = $prompts;
            $idea->update([
                'status' => 'generating_images',
                'generated_article' => $article,
            ]);

            \Illuminate\Support\Facades\Log::info("[AutoMode] Idea #{$idea->id}: auto-started image generation for " . count($prompts) . " images");
        }

        return response()->json(['success' => true, 'data' => $idea->fresh()]);
    });
    Route::get('/blog/image-status/{postId}', [\App\Http\Controllers\Api\BlogPipelineController::class, 'imageStatus']);

    // Split pipeline endpoints — used by article-prep/write/score skills
    Route::get('/content-ideas/{id}', function ($id) {
        $idea = \App\Models\ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Idea not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $idea]);
    });

    Route::get('/content-ideas/{id}/mechanical-scores', function ($id) {
        $idea = \App\Models\ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Idea not found.'], 404);
        }
        $article = $idea->generated_article ?? [];
        $title = data_get($article, 'title', $idea->title ?? '');
        $content = data_get($article, 'content', '');
        $keyword = data_get($article, 'keyword', data_get($article, 'prep_data.keyword', ''));
        $language = data_get($article, 'language', 'id');

        if ($content === '' || $title === '') {
            return response()->json([
                'success' => false,
                'message' => 'Article content or title missing. Save article data first via /save-article.',
            ], 409);
        }

        $scorer = app(\App\Services\MechanicalScoringService::class);
        $scores = $scorer->analyze($title, $content, $keyword, [
            'language' => $language,
            'current_year' => (int) date('Y'),
        ]);

        return response()->json(['success' => true, 'data' => $scores]);
    });

    Route::put('/content-ideas/{id}/save-prep', function (\Illuminate\Http\Request $request, $id) {
        $idea = \App\Models\ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Idea not found.'], 404);
        }

        $prepData = $request->input('prep_data', []);

        // Defense: prep_data must be a populated object. Reject shell-quoted
        // payload corruption (where the body becomes {"<url>": null}).
        if (!is_array($prepData) || empty($prepData) || !isset($prepData['outline'])) {
            \Illuminate\Support\Facades\Log::warning('[save-prep] rejected malformed body', [
                'idea_id' => $id,
                'keys_received' => array_keys($request->all()),
                'raw_body_preview' => substr($request->getContent(), 0, 300),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid prep_data. Body must be JSON with prep_data.outline at minimum. Use file-based curl (curl -d @file.json) to avoid shell quoting issues.',
                'keys_received' => array_keys($request->all()),
            ], 400);
        }

        $existing = $idea->generated_article ?? [];
        $existing['prep_data'] = $prepData;
        $idea->update(['generated_article' => $existing]);

        return response()->json(['success' => true, 'message' => 'Prep data saved.']);
    });

    // Viral-First Pipeline v3 — Phase B.5: persist lean research schema v2
    // (data_points, quotes, entities, personas, written_guides) to top-level
    // research_data column. Mirrors save-prep's shape/error envelope.
    Route::put('/content-ideas/{id}/save-research', function (\Illuminate\Http\Request $request, $id) {
        $idea = \App\Models\ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Idea not found.'], 404);
        }

        $researchData = $request->input('research_data', []);
        if (!is_array($researchData) || empty($researchData) || !isset($researchData['data_points'])) {
            \Illuminate\Support\Facades\Log::warning('[save-research] rejected malformed body', [
                'idea_id' => $id,
                'keys_received' => array_keys($request->all()),
                'raw_body_preview' => substr($request->getContent(), 0, 300),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid research_data. Body must be JSON with research_data.data_points at minimum. Use file-based curl (curl -d @file.json) to avoid shell quoting issues.',
                'keys_received' => array_keys($request->all()),
            ], 400);
        }

        $idea->update(['research_data' => $researchData]);

        return response()->json(['success' => true, 'message' => 'Research data saved.']);
    });

    Route::put('/content-ideas/{id}/save-article', function (\Illuminate\Http\Request $request, $id) {
        $idea = \App\Models\ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Idea not found.'], 404);
        }

        $existing = $idea->generated_article ?? [];
        // Merge article data, preserving prep_data
        $articleData = $request->except(['prep_data']);

        // Defense: reject malformed bodies where shell quoting mangled the
        // curl payload (e.g. keys like "/automation/..." with null values).
        // See debug 2026-04-18: idea #74 saved a URL path as the only key
        // because bash split the -d argument on unescaped quotes in content.
        $hasTitle = isset($articleData['title']) && is_string($articleData['title']) && trim($articleData['title']) !== '';
        $hasContent = isset($articleData['content']) && is_string($articleData['content']) && trim($articleData['content']) !== '';
        if (!$hasTitle || !$hasContent) {
            \Illuminate\Support\Facades\Log::warning('[save-article] rejected malformed body', [
                'idea_id' => $id,
                'keys_received' => array_keys($articleData),
                'content_type' => $request->header('Content-Type'),
                'raw_body_preview' => substr($request->getContent(), 0, 300),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Missing required fields. Body must be JSON with title (non-empty) and content (non-empty). Re-send using file-based curl (curl -d @prompt.json) to avoid shell quoting issues.',
                'keys_received' => array_keys($articleData),
            ], 400);
        }

        $idea->update(['generated_article' => array_merge($existing, $articleData)]);

        return response()->json(['success' => true, 'message' => 'Article data saved.']);
    });

    Route::post('/content-ideas/{id}/continue-pipeline', function (\Illuminate\Http\Request $request, $id) {
        $idea = \App\Models\ContentIdea::find($id);
        if (!$idea) {
            return response()->json(['success' => false, 'message' => 'Idea not found.'], 404);
        }

        $service = app(\App\Services\ArticleGenerationService::class);

        // Explicit phase handoff: article-images → GeminiGen (Gate 2 split flow)
        $phase = $request->input('phase');
        if ($phase === 'images') {
            $article = $idea->generated_article ?? [];
            if (empty(data_get($article, 'image_prompts'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot continue to GeminiGen: no image_prompts saved yet.',
                ], 409);
            }

            $imageService = app(\App\Services\ImageGenerationService::class);
            $prompts = data_get($article, 'image_prompts', []);
            $queuedJobs = [];

            foreach ($prompts as $index => $item) {
                $uuid = $imageService->queue(
                    postId: null,
                    prompt: $item['prompt'] ?? '',
                    type: ($item['type'] ?? 'inline') === 'cover' ? 'hero' : 'inline',
                    insertAfterHeading: $item['insert_after_heading'] ?? null,
                    model: $item['model'] ?? config('content.default_image_model', 'nano-banana-pro'),
                    aspectRatio: $item['aspect_ratio'] ?? '16:9',
                    style: $item['style'] ?? 'Cinematic',
                    faceRefs: $item['face_refs'] ?? [],
                    styleRefs: $item['style_refs'] ?? [],
                    additionalNotes: $item['additional_notes'] ?? ''
                );

                if ($uuid) {
                    $prompts[$index]['job_uuid'] = $uuid;
                    $prompts[$index]['status'] = 'generating';
                    $queuedJobs[] = $uuid;
                }
            }

            $article['image_prompts'] = $prompts;
            $idea->update([
                'generated_article' => $article,
                'status' => 'generating_images',
                'progress_percentage' => 30,
                'current_step' => 'gemini_gen',
            ]);

            return response()->json([
                'success' => true,
                'next_phase' => 'gemini_gen',
                'queued' => count($queuedJobs),
            ]);
        }

        // Images resume — user uploaded brand refs, resume article-images skill
        if ($request->input('phase') === 'images_resume') {
            $idea->update([
                'status' => 'generating_images',
                'current_step' => 'authoring_image_prompts',
                'progress_percentage' => 5,
            ]);
            $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
            $result = $service->triggerImages($idea->id, $idempotencyKey);
            return response()->json([
                'success' => true,
                'next_phase' => 'images_resume',
                'pid' => $result['pid'] ?? null,
            ]);
        }

        $progress = $idea->progress_percentage ?? 0;

        // Prep done (35%) → trigger write
        if ($progress >= 35 && $progress < 85) {
            $result = $service->triggerWrite($idea->id);
            $idea->update(['process_pid' => $result['pid']]);
            return response()->json(['success' => true, 'next_phase' => 'write', 'pid' => $result['pid']]);
        }

        // Write done (85%) → trigger score OR skip per Phase C env flag
        if ($progress >= 85 && $progress < 100) {
            $useScorePhase = (bool) config('services.article_generation.use_score_phase', false);

            if (!$useScorePhase) {
                // Phase C default path: skip AI /article-score, capture mechanical
                // metrics inline, flip idea to article_ready so downstream (image
                // generation) advances without a Sonnet call.
                $snapshot = app(\App\Services\MechanicalSnapshotWriter::class)->captureFor($idea);
                $snapshotSaved = !isset($snapshot['error']);

                $idea->update([
                    'status' => 'article_ready',
                    'progress_percentage' => 100,
                    'current_step' => 'mechanical_snapshot',
                    'process_pid' => null,
                ]);

                return response()->json([
                    'success' => true,
                    'next_phase' => 'score_skipped',
                    'mechanical_snapshot_saved' => $snapshotSaved,
                ]);
            }

            $result = $service->triggerScore($idea->id);
            $idea->update(['process_pid' => $result['pid']]);
            return response()->json(['success' => true, 'next_phase' => 'score', 'pid' => $result['pid']]);
        }

        return response()->json(['success' => false, 'message' => 'No next phase to trigger.', 'progress' => $progress]);
    });

    // Gate 2 split flow: /article-images skill saves authored prompts
    Route::put('/content-ideas/{id}/save-image-prompts', [ContentIdeaController::class, 'saveImagePrompts']);

    // Finalize: /article-translate skill endpoints (post-level, not idea-level)
    Route::get('/posts/{id}/for-translation', [ContentIdeaController::class, 'getPostForTranslation']);
    Route::put('/posts/{id}/save-translation', [ContentIdeaController::class, 'saveTranslation']);
    Route::post('/posts/{id}/translation-complete', [ContentIdeaController::class, 'markTranslationComplete']);
    Route::put('/posts/{id}/progress', [ContentIdeaController::class, 'postProgress']);

    // Carousel endpoints (protected)
    Route::get('/carousel/accounts', [CarouselDraftController::class, 'listAccounts']);
    Route::get('/carousel/drafts', [CarouselDraftController::class, 'index']);
    Route::get('/carousel/drafts/{id}', [CarouselDraftController::class, 'show']);
});

// Admin Automation Management Routes
Route::middleware(['auth:sanctum'])->prefix('admin/automation')->group(function () {
    // Token management
    Route::get('/tokens', [TokenController::class, 'index']);
    Route::post('/tokens', [TokenController::class, 'store']);
    Route::delete('/tokens/{id}', [TokenController::class, 'destroy']);

    // Logs management
    Route::get('/logs', [TokenController::class, 'logs']);
    Route::delete('/logs', [TokenController::class, 'clearLogs']);
});

// Admin Carousel Drafts Routes
Route::middleware(['auth:sanctum'])->prefix('admin/carousel-drafts')->group(function () {
    Route::get('/', [CarouselDraftController::class, 'index']);
    Route::get('/{id}', [CarouselDraftController::class, 'show']);
    Route::post('/{id}/approve', [CarouselDraftController::class, 'approve']);
    Route::post('/{id}/reject', [CarouselDraftController::class, 'reject']);
    Route::post('/{id}/schedule', [CarouselDraftController::class, 'schedule']);
    Route::patch('/{id}/slides/{slideId}/status', [CarouselDraftController::class, 'updateSlideStatus']);
});

// Admin Content Engine Routes
Route::middleware(['auth:sanctum'])->prefix('admin/content-engine')->group(function () {
    // Health & workflows (Content Engine proxy)
    Route::get('/health', [ContentIdeaController::class, 'healthCheck']);
    Route::get('/workflows', [ContentIdeaController::class, 'listWorkflows']);
    Route::get('/workflows/{id}', [ContentIdeaController::class, 'getWorkflowStatus']);

    // Content Ideas CRUD
    Route::get('/ideas', [ContentIdeaController::class, 'index']);
    Route::post('/ideas', [ContentIdeaController::class, 'store']);
    Route::get('/ideas/{id}', [ContentIdeaController::class, 'show']);
    Route::put('/ideas/{id}', [ContentIdeaController::class, 'update']);
    Route::delete('/ideas/{id}', [ContentIdeaController::class, 'destroy']);
    Route::post('/ideas/{id}/archive', [ContentIdeaController::class, 'archive']);
    Route::post('/ideas/{id}/restore', [ContentIdeaController::class, 'restore']);
    Route::post('/ideas/{id}/revert', [ContentIdeaController::class, 'revertToDraft']);
    Route::post('/ideas/{id}/resume', [ContentIdeaController::class, 'resumePipeline']);

    // Trending topics
    Route::get('/trending', [ContentIdeaController::class, 'pullTrending']);
    Route::post('/trending/score-batch', [ContentIdeaController::class, 'scoreTrendingBatch']);
    Route::post('/trending/import', [ContentIdeaController::class, 'importTrending']);

    // Pipeline: Gate 1 (Article)
    Route::post('/ideas/{id}/research', [ContentIdeaController::class, 'startResearch']);
    Route::get('/ideas/{id}/research', [ContentIdeaController::class, 'getResearch']);
    Route::get('/ideas/{id}/progress', [ContentIdeaController::class, 'getProgress']);
    Route::post('/ideas/{id}/approve-article', [ContentIdeaController::class, 'approveArticle']);
    Route::post('/ideas/{id}/regenerate', [ContentIdeaController::class, 'regenerateArticle']);
    Route::post('/ideas/{id}/run-deep-score', [ContentIdeaController::class, 'runDeepScore']);

    // Pipeline: Gate 2 (Images)
    Route::put('/ideas/{id}/save-draft', [ContentIdeaController::class, 'saveDraft']);
    Route::post('/ideas/{id}/generate-images', [ContentIdeaController::class, 'startImageGeneration']);
    Route::post('/ideas/{id}/generate-segment-image', [ContentIdeaController::class, 'generateSegmentImage']);
    Route::post('/ideas/{id}/rewrite-vd', [ContentIdeaController::class, 'rewriteSegmentVd']);
    Route::post('/ideas/{id}/translate-article', [ContentIdeaController::class, 'translateArticle']);
    Route::post('/ideas/{id}/publish', [ContentIdeaController::class, 'approveAndPublish']);

    // Pipeline: Gate 2 split flow — per-section concept editing + prompt regeneration
    Route::put('/ideas/{id}/update-image-concept', [ContentIdeaController::class, 'updateImageConcept']);
    Route::post('/ideas/{id}/regenerate-image-prompts', [ContentIdeaController::class, 'regenerateImagePrompts']);

    // Stock image search (Pexels + Unsplash proxy)
    Route::get('/stock-images/search', [\App\Http\Controllers\Api\Admin\StockImageController::class, 'search']);

    // Reference image upload for image generation
    Route::post('/upload-reference', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);

        $path = $request->file('file')->store('content-engine/references', 'public');

        return response()->json([
            'success' => true,
            'data' => ['url' => url('/storage/' . $path)],
        ]);
    });

    // Download stock image to local storage (Pexels/Unsplash → /storage/blog-images/)
    Route::post('/download-stock-image', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'url' => 'required|url|max:2000',
        ]);

        $sourceUrl = $request->input('url');
        try {
            $imageData = \Illuminate\Support\Facades\Http::timeout(30)->get($sourceUrl)->body();
            if (empty($imageData)) {
                return response()->json(['success' => false, 'message' => 'Empty response from image URL.'], 422);
            }

            $ext = pathinfo(parse_url($sourceUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            $filename = 'stock-' . uniqid() . '.' . $ext;
            $path = 'blog-images/' . $filename;

            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $imageData);

            return response()->json([
                'success' => true,
                'data' => ['url' => url('/storage/' . $path)],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download stock image: ' . $e->getMessage(),
            ], 500);
        }
    });

    // Cleanup non-selected variation images from storage
    Route::post('/cleanup-variation-images', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'urls_to_delete' => 'required|array|min:1',
            'urls_to_delete.*' => 'string|max:2000',
        ]);

        $deleted = 0;
        $storageBase = url('/storage/');
        foreach ($request->input('urls_to_delete') as $imageUrl) {
            if (!str_starts_with($imageUrl, $storageBase)) continue;
            $relativePath = str_replace($storageBase . '/', '', $imageUrl);
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($relativePath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($relativePath);
                $deleted++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => ['deleted' => $deleted],
        ]);
    });
});
