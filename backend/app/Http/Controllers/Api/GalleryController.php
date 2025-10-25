<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGalleryRequest;
use App\Http\Requests\UpdateGalleryRequest;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GalleryController extends Controller
{
    /**
     * Display a listing of galleries.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Gallery::with(['award', 'items']);

        // Filter by award
        if ($request->has('award_id')) {
            $query->where('award_id', $request->query('award_id'));
        }

        // Filter by company
        if ($request->has('company')) {
            $query->where('company', 'like', '%' . $request->query('company') . '%');
        }

        // Filter by period
        if ($request->has('period')) {
            $query->where('period', $request->query('period'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->query('is_active'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('period', 'like', "%{$search}%");
            });
        }

        // Order by
        $orderBy = $request->query('order_by', 'sort_order');
        $orderDir = $request->query('order_dir', 'asc');
        $query->orderBy($orderBy, $orderDir);

        $galleries = $query->withCount('items')->paginate(12);

        return response()->json([
            'success' => true,
            'data' => GalleryResource::collection($galleries),
            'links' => [
                'first' => $galleries->url(1),
                'last' => $galleries->url($galleries->lastPage()),
                'prev' => $galleries->previousPageUrl(),
                'next' => $galleries->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $galleries->currentPage(),
                'from' => $galleries->firstItem(),
                'last_page' => $galleries->lastPage(),
                'per_page' => $galleries->perPage(),
                'to' => $galleries->lastItem(),
                'total' => $galleries->total(),
            ],
        ]);
    }

    /**
     * Display the specified gallery.
     */
    public function show(string $id): JsonResponse
    {
        $gallery = Gallery::with(['award', 'items'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new GalleryResource($gallery),
        ]);
    }

    /**
     * Store a newly created gallery.
     */
    public function store(StoreGalleryRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Handle thumbnail upload
            if ($request->hasFile('thumbnail')) {
                $thumbnail = $request->file('thumbnail');
                $filename = time() . '_' . Str::slug($data['title']) . '.' . $thumbnail->getClientOriginalExtension();
                $path = $thumbnail->storeAs('gallery/thumbnails', $filename, 'public');
                $data['thumbnail'] = $path;
            }

            // Set sort_order if not provided
            if (!isset($data['sort_order'])) {
                $maxOrder = Gallery::max('sort_order') ?? 0;
                $data['sort_order'] = $maxOrder + 1;
            }

            // Set is_active default
            $data['is_active'] = $data['is_active'] ?? true;

            $gallery = Gallery::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gallery created successfully',
                'data' => new GalleryResource($gallery->load('award')),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if it exists
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Failed to create gallery', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create gallery',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Update the specified gallery.
     */
    public function update(UpdateGalleryRequest $request, string $id): JsonResponse
    {
        $gallery = Gallery::findOrFail($id);

        try {
            DB::beginTransaction();

            $data = $request->validated();
            $oldThumbnailPath = null;

            // Handle thumbnail upload if new thumbnail provided
            if ($request->hasFile('thumbnail')) {
                // Store old path for cleanup after successful update
                $oldThumbnailPath = $gallery->thumbnail;

                $thumbnail = $request->file('thumbnail');
                $filename = time() . '_' . Str::slug($request->input('title', $gallery->title)) . '.' . $thumbnail->getClientOriginalExtension();
                $path = $thumbnail->storeAs('gallery/thumbnails', $filename, 'public');
                $data['thumbnail'] = $path;
            }

            $gallery->update($data);

            // Delete old thumbnail after successful update
            if ($oldThumbnailPath && Storage::disk('public')->exists($oldThumbnailPath)) {
                Storage::disk('public')->delete($oldThumbnailPath);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gallery updated successfully',
                'data' => new GalleryResource($gallery->load(['award', 'items'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up new uploaded file if it exists
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Failed to update gallery', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update gallery',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Remove the specified gallery.
     * Note: Gallery items will be cascade deleted due to FK constraint.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $gallery = Gallery::with('items')->findOrFail($id);

            DB::beginTransaction();

            // Collect all file paths for cleanup (thumbnail + all item files)
            $filesToDelete = [];

            if ($gallery->thumbnail) {
                $filesToDelete[] = $gallery->thumbnail;
            }

            foreach ($gallery->items as $item) {
                if ($item->file_path) {
                    $filesToDelete[] = $item->file_path;
                }
            }

            // Delete gallery (items will cascade delete)
            $gallery->delete();

            DB::commit();

            // Clean up files after successful database deletion
            foreach ($filesToDelete as $filePath) {
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Gallery and all items deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete gallery', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete gallery',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
