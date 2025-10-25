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

// Public Contact Route (Rate Limited)
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,60'); // 5 requests per minute

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
    Route::get('/{group}', [SettingController::class, 'getByGroup']);
});

// SEO Sitemap Routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-index.xml', [SitemapController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts'])->name('sitemap.posts');
Route::get('/sitemap-projects.xml', [SitemapController::class, 'projects'])->name('sitemap.projects');

// Health check
Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));

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
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('automation')->group(function () {
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
