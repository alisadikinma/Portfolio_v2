<?php

use Illuminate\Support\Facades\Route;
use App\Models\Project;
use App\Models\Post;

Route::get('/', function () {
    return view('welcome');
});

// Redirect login route to Filament admin login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Named routes for frontend (used by sitemap)
Route::get('/', function () { return redirect('http://localhost:5173'); })->name('home');
Route::get('/blog', function () { return redirect('http://localhost:5173/blog'); })->name('blog');
Route::get('/projects', function () { return redirect('http://localhost:5173/projects'); })->name('projects');
Route::get('/about', function () { return redirect('http://localhost:5173/about'); })->name('about');
Route::get('/contact', function () { return redirect('http://localhost:5173/contact'); })->name('contact');

// Test route to verify routing works
Route::get('/test-route', function () {
    return 'Routes are working! User: ' . (auth()->check() ? auth()->user()->email : 'Not logged in');
});

// SSR for bots - Projects
Route::get('/projects/{slug}', function ($slug) {
    $project = Project::where('slug', $slug)->first();
    
    if (!$project) {
        abort(404);
    }
    
    $baseUrl = rtrim(config('app.url'), '/');
    
    // Get og_image
    $ogImage = $project->og_image;
    if ($ogImage && !str_starts_with($ogImage, 'http')) {
        $ogImage = $baseUrl . $ogImage;
    } elseif (!$ogImage && $project->image) {
        $imagePath = str_starts_with($project->image, '/') 
            ? $project->image 
            : '/storage/' . $project->image;
        $ogImage = $baseUrl . $imagePath;
    }
    
    $title = htmlspecialchars($project->meta_title ?: $project->title);
    $description = htmlspecialchars($project->meta_description ?: $project->description);
    $url = htmlspecialchars($baseUrl . '/projects/' . $slug);
    $image = htmlspecialchars($ogImage);
    
    // Load index.html
    $html = file_get_contents(base_path('../../frontend/dist/index.html'));
    
    // Replace Open Graph meta tags
    $html = preg_replace('/<meta property="og:title" content="[^"]*">/', '<meta property="og:title" content="' . $title . '">', $html);
    $html = preg_replace('/<meta property="og:description" content="[^"]*">/', '<meta property="og:description" content="' . $description . '">', $html);
    $html = preg_replace('/<meta property="og:image" content="[^"]*">/', '<meta property="og:image" content="' . $image . '">', $html);
    $html = preg_replace('/<meta property="og:url" content="[^"]*">/', '<meta property="og:url" content="' . $url . '">', $html);
    
    // Replace Twitter Card meta tags
    $html = preg_replace('/<meta name="twitter:title" content="[^"]*">/', '<meta name="twitter:title" content="' . $title . '">', $html);
    $html = preg_replace('/<meta name="twitter:description" content="[^"]*">/', '<meta name="twitter:description" content="' . $description . '">', $html);
    $html = preg_replace('/<meta name="twitter:image" content="[^"]*">/', '<meta name="twitter:image" content="' . $image . '">', $html);
    
    return response($html)->header('Content-Type', 'text/html');
});

// SSR for bots - Blog
Route::get('/blog/{slug}', function ($slug) {
    $post = Post::where('slug', $slug)->first();
    
    if (!$post) {
        abort(404);
    }
    
    $baseUrl = rtrim(config('app.url'), '/');
    
    // Get og_image
    $ogImage = $post->og_image;
    if ($ogImage && !str_starts_with($ogImage, 'http')) {
        $ogImage = $baseUrl . $ogImage;
    } elseif (!$ogImage && $post->featured_image) {
        $imagePath = str_starts_with($post->featured_image, '/') 
            ? $post->featured_image 
            : '/storage/' . $post->featured_image;
        $ogImage = $baseUrl . $imagePath;
    }
    
    $title = htmlspecialchars($post->meta_title ?: $post->title);
    $description = htmlspecialchars($post->meta_description ?: $post->excerpt);
    $url = htmlspecialchars($baseUrl . '/blog/' . $slug);
    $image = htmlspecialchars($ogImage);
    
    // Load index.html
    $html = file_get_contents(base_path('../../frontend/dist/index.html'));
    
    // Replace Open Graph meta tags
    $html = preg_replace('/<meta property="og:title" content="[^"]*">/', '<meta property="og:title" content="' . $title . '">', $html);
    $html = preg_replace('/<meta property="og:description" content="[^"]*">/', '<meta property="og:description" content="' . $description . '">', $html);
    $html = preg_replace('/<meta property="og:image" content="[^"]*">/', '<meta property="og:image" content="' . $image . '">', $html);
    $html = preg_replace('/<meta property="og:url" content="[^"]*">/', '<meta property="og:url" content="' . $url . '">', $html);
    
    // Replace Twitter Card meta tags
    $html = preg_replace('/<meta name="twitter:title" content="[^"]*">/', '<meta name="twitter:title" content="' . $title . '">', $html);
    $html = preg_replace('/<meta name="twitter:description" content="[^"]*">/', '<meta name="twitter:description" content="' . $description . '">', $html);
    $html = preg_replace('/<meta name="twitter:image" content="[^"]*">/', '<meta name="twitter:image" content="' . $image . '">', $html);
    
    return response($html)->header('Content-Type', 'text/html');
});
