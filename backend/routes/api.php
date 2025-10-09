<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AwardController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public Awards Routes
Route::prefix('awards')->group(function () {
    Route::get('/', [AwardController::class, 'index']);
    Route::get('/{id}', [AwardController::class, 'show']);
    Route::get('/{id}/galleries', [AwardController::class, 'getGalleries']);
});

// Admin Awards Routes (Protected)
Route::middleware(['auth:sanctum'])->prefix('admin/awards')->group(function () {
    Route::post('/{id}/galleries', [AwardController::class, 'linkGallery']);
    Route::delete('/{id}/galleries/{galleryId}', [AwardController::class, 'unlinkGallery']);
    Route::put('/{id}/galleries/reorder', [AwardController::class, 'reorderGalleries']);
});
