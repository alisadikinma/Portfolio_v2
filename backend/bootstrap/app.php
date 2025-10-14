<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Register Livewire routes
            \Livewire\Livewire::setUpdateRoute(function ($handle) {
                return \Illuminate\Support\Facades\Route::post('livewire/update', $handle);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add CORS middleware for API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Note: statefulApi() removed - we're using token-based auth, not cookie-based
        // This prevents CSRF 419 errors on API routes
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
