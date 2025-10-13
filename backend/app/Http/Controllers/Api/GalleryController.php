<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max
            'category' => 'required|string|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

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

            return response()->json([
                'message' => 'Gallery item created successfully',
                'data' => new GalleryResource($gallery),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create gallery item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified gallery item.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $gallery = Gallery::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'category' => 'sometimes|required|string|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Handle image upload if new image provided
            if ($request->hasFile('image')) {
                // Delete old image
                if ($gallery->image && Storage::disk('public')->exists($gallery->image)) {
                    Storage::disk('public')->delete($gallery->image);
                }

                $image = $request->file('image');
                $filename = time() . '_' . Str::slug($request->input('title', $gallery->title)) . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('gallery', $filename, 'public');
                $data['image'] = $path;
            }

            $gallery->update($data);

            return response()->json([
                'message' => 'Gallery item updated successfully',
                'data' => new GalleryResource($gallery),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update gallery item',
                'error' => $e->getMessage(),
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
            $images = $request->file('images');
            $category = $request->input('category');
            $titles = $request->input('titles', []);
            $descriptions = $request->input('descriptions', []);

            $maxOrder = Gallery::where('category', $category)->max('order') ?? 0;
            $uploaded = [];

            foreach ($images as $index => $image) {
                $title = $titles[$index] ?? 'Image ' . ($index + 1);
                $description = $descriptions[$index] ?? null;

                $filename = time() . '_' . $index . '_' . Str::slug($title) . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('gallery', $filename, 'public');

                $gallery = Gallery::create([
                    'title' => $title,
                    'description' => $description,
                    'image' => $path,
                    'category' => $category,
                    'order' => $maxOrder + $index + 1,
                ]);

                $uploaded[] = new GalleryResource($gallery);
            }

            return response()->json([
                'message' => count($uploaded) . ' gallery items uploaded successfully',
                'data' => $uploaded,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload gallery items',
                'error' => $e->getMessage(),
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
            $ids = $request->input('ids');
            $galleries = Gallery::whereIn('id', $ids)->get();

            foreach ($galleries as $gallery) {
                // Delete image file
                if ($gallery->image && Storage::disk('public')->exists($gallery->image)) {
                    Storage::disk('public')->delete($gallery->image);
                }
                $gallery->delete();
            }

            return response()->json([
                'message' => count($galleries) . ' gallery items deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete gallery items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
