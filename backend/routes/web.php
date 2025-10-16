<?php

use Illuminate\Support\Facades\Route;

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
