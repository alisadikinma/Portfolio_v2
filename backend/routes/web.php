<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShortLinkController;

// Redirect login route to Filament admin login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

Route::get('/test-route', function () {
    return 'Routes are working! User: ' . (auth()->check() ? auth()->user()->email : 'Not logged in');
});

// Branded URL shortener — /r/{code} → 301 redirect to short_links.target_url
// (which already carries UTM params for GA attribution). Used by cross-post
// pipeline (LinkedIn first-comment, IG/Threads first-comment via Publer,
// TikTok caption body). See App\Services\ShortLinkService.
Route::get('/r/{code}', [ShortLinkController::class, 'redirect'])
    ->where('code', '[A-Za-z0-9]{4,16}')
    ->name('short-link.redirect');
