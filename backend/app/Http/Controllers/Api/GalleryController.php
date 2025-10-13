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
     * Display a listing of gallery items.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Gallery::query();

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->query('category'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Order by
        $orderBy = $request->query('order_by', 'order');
        $orderDir = $request->query('order_dir', 'asc');
        $query->orderBy($orderBy, $orderDir);

        $galleries = $query->paginate(12);

        return response()->json([
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
     * Display the specified gallery item.
     */
    public function show(string $id): JsonResponse
    {
        $gallery = Gallery::findOrFail($id);

        return response()->json([
            'data' => new GalleryResource($gallery),
        ]);
    }

    /**
     * Store a newly created gallery item.
     */
    public function store(StoreGalleryRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = time() . '_' . Str::slug($data['title']) . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('gallery', $filename, 'public');
                $data['image'] = $path;
            }

            // Set order if not provided
            if (!isset($data['order'])) {
                $maxOrder = Gallery::where('category', $data['category'])->max('order') ?? 0;
                $data['order'] = $maxOrder + 1;
            }

            $gallery = Gallery::create($data);

            DB::commit();

            return response()->json([
                'message' => 'Gallery item created successfully',
                'data' => new GalleryResource($gallery),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if it exists
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Failed to create gallery item', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create gallery item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Update the specified gallery item.
     */
    public function update(UpdateGalleryRequest $request, string $id): JsonResponse
    {
        $gallery = Gallery::findOrFail($id);

        try {
            DB::beginTransaction();

            $data = $request->validated();
            $oldImagePath = null;

            // Handle image upload if new image provided
            if ($request->hasFile('image')) {
                // Store old path for cleanup after successful update
                $oldImagePath = $gallery->image;

                $image = $request->file('image');
                $filename = time() . '_' . Str::slug($request->input('title', $gallery->title)) . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('gallery', $filename, 'public');
                $data['image'] = $path;
            }

            $gallery->update($data);

            // Delete old image after successful update
            if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
            }

            DB::commit();

            return response()->json([
                'message' => 'Gallery item updated successfully',
                'data' => new GalleryResource($gallery),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up new uploaded file if it exists
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Failed to update gallery item', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to update gallery item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Remove the specified gallery item.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $gallery = Gallery::findOrFail($id);

            // Delete image file
            if ($gallery->image && Storage::disk('public')->exists($gallery->image)) {
                Storage::disk('public')->delete($gallery->image);
            }

            $gallery->delete();

            return response()->json([
                'message' => 'Gallery item deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete gallery item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk upload gallery items.
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array|min:1|max:20',
            'images.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'category' => 'required|string|max:100',
            'titles' => 'nullable|array',
            'titles.*' => 'nullable|string|max:255',
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $images = $request->file('images');
            $category = $request->input('category');
            $titles = $request->input('titles', []);
            $descriptions = $request->input('descriptions', []);

            $maxOrder = Gallery::where('category', $category)->max('order') ?? 0;
            $uploaded = [];
            $uploadedPaths = [];

            foreach ($images as $index => $image) {
                $title = $titles[$index] ?? 'Image ' . ($index + 1);
                $description = $descriptions[$index] ?? null;

                $filename = time() . '_' . $index . '_' . Str::slug($title) . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('gallery', $filename, 'public');
                $uploadedPaths[] = $path;

                $gallery = Gallery::create([
                    'title' => $title,
                    'description' => $description,
                    'image' => $path,
                    'category' => $category,
                    'order' => $maxOrder + $index + 1,
                ]);

                $uploaded[] = new GalleryResource($gallery);
            }

            DB::commit();

            return response()->json([
                'message' => count($uploaded) . ' gallery items uploaded successfully',
                'data' => $uploaded,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded files on failure
            foreach ($uploadedPaths ?? [] as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            Log::error('Failed to bulk upload gallery items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to upload gallery items',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Bulk delete gallery items.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:galleries,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $ids = $request->input('ids');
            $galleries = Gallery::whereIn('id', $ids)->get();

            // Collect image paths for cleanup
            $imagePaths = $galleries->pluck('image')->filter()->toArray();

            // Delete all galleries
            Gallery::whereIn('id', $ids)->delete();

            DB::commit();

            // Delete image files after successful database deletion
            foreach ($imagePaths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            return response()->json([
                'message' => count($galleries) . ' gallery items deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to bulk delete gallery items', [
                'ids' => $request->input('ids'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to delete gallery items',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
