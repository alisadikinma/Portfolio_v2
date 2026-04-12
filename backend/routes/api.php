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

    // Content Ideas Pipeline (for Claude Remote Trigger agent)
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
    Route::put('/content-ideas/{id}/complete', function (\Illuminate\Http\Request $request, $id) {
        $idea = \App\Models\ContentIdea::findOrFail($id);
        $idea->update([
            'status' => 'article_ready',
            'generated_article' => $request->input('generated_article'),
            'research_data' => $request->input('research_data'),
        ]);

        return response()->json(['success' => true, 'data' => $idea->fresh()]);
    });
    Route::get('/blog/image-status/{postId}', [\App\Http\Controllers\Api\BlogPipelineController::class, 'imageStatus']);

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
    Route::put('/ideas/{id}', [ContentIdeaController::class, 'update']);
    Route::delete('/ideas/{id}', [ContentIdeaController::class, 'destroy']);
    Route::post('/ideas/{id}/archive', [ContentIdeaController::class, 'archive']);
    Route::post('/ideas/{id}/restore', [ContentIdeaController::class, 'restore']);
    Route::post('/ideas/{id}/revert', [ContentIdeaController::class, 'revertToDraft']);

    // Trending topics
    Route::get('/trending', [ContentIdeaController::class, 'pullTrending']);
    Route::post('/trending/import', [ContentIdeaController::class, 'importTrending']);

    // Pipeline: Gate 1 (Article)
    Route::post('/ideas/{id}/research', [ContentIdeaController::class, 'startResearch']);
    Route::get('/ideas/{id}/research', [ContentIdeaController::class, 'getResearch']);
    Route::post('/ideas/{id}/approve-article', [ContentIdeaController::class, 'approveArticle']);

    // Pipeline: Gate 2 (Images)
    Route::post('/ideas/{id}/generate-images', [ContentIdeaController::class, 'startImageGeneration']);
    Route::post('/ideas/{id}/publish', [ContentIdeaController::class, 'approveAndPublish']);
});
