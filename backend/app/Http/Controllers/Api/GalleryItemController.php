<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryItemResource;
use App\Models\Gallery;
use App\Models\GalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GalleryItemController extends Controller
{
    /**
     * Display a listing of gallery items for a specific gallery.
     */
    public function index(Request $request, string $galleryId): JsonResponse
    {
        $gallery = Gallery::findOrFail($galleryId);

        $query = $gallery->items();

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        // Order by sequence
        $query->orderBy('sequence');

        $items = $query->get();

        return response()->json([
            'success' => true,
            'data' => GalleryItemResource::collection($items),
        ]);
    }

    /**
     * Store a newly created gallery item.
     */
    public function store(Request $request, string $galleryId): JsonResponse
    {
        $gallery = Gallery::findOrFail($galleryId);

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:image,video',
            'file' => 'required_if:type,image|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'file_path' => 'required_if:type,video|string|max:500',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sequence' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $data['gallery_id'] = $gallery->id;

            // Handle file upload for images
            if ($request->hasFile('file') && $data['type'] === 'image') {
                $file = $request->file('file');
                $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('gallery/items', $filename, 'public');
                $data['file_path'] = $path;
                unset($data['file']);
            }

            // Set sequence if not provided
            if (!isset($data['sequence'])) {
                $maxSequence = $gallery->items()->max('sequence') ?? -1;
                $data['sequence'] = $maxSequence + 1;
            }

            $item = GalleryItem::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gallery item created successfully',
                'data' => new GalleryItemResource($item),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if it exists
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Failed to create gallery item', [
                'gallery_id' => $galleryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create gallery item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Display the specified gallery item.
     */
    public function show(string $galleryId, string $id): JsonResponse
    {
        $gallery = Gallery::findOrFail($galleryId);
        $item = $gallery->items()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new GalleryItemResource($item),
        ]);
    }

    /**
     * Update the specified gallery item.
     */
    public function update(Request $request, string $galleryId, string $id): JsonResponse
    {
        $gallery = Gallery::findOrFail($galleryId);
        $item = $gallery->items()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:image,video',
            'file' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'file_path' => 'nullable|string|max:500',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sequence' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $oldFilePath = null;

            // Handle file upload if new file provided
            if ($request->hasFile('file')) {
                $oldFilePath = $item->file_path;

                $file = $request->file('file');
                $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('gallery/items', $filename, 'public');
                $data['file_path'] = $path;
                unset($data['file']);
            }

            $item->update($data);

            // Delete old file after successful update
            if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                Storage::disk('public')->delete($oldFilePath);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gallery item updated successfully',
                'data' => new GalleryItemResource($item),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up new uploaded file if it exists
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Failed to update gallery item', [
                'gallery_id' => $galleryId,
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update gallery item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Remove the specified gallery item.
     */
    public function destroy(string $galleryId, string $id): JsonResponse
    {
        try {
            $gallery = Gallery::findOrFail($galleryId);
            $item = $gallery->items()->findOrFail($id);

            DB::beginTransaction();

            $filePath = $item->file_path;

            $item->delete();

            DB::commit();

            // Clean up file after successful deletion
            if ($filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Gallery item deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete gallery item', [
                'gallery_id' => $galleryId,
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete gallery item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Bulk upload gallery items.
     */
    public function bulkUpload(Request $request, string $galleryId): JsonResponse
    {
        $gallery = Gallery::findOrFail($galleryId);

        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'titles' => 'nullable|array',
            'titles.*' => 'nullable|string|max:255',
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $files = $request->file('files');
            $titles = $request->input('titles', []);
            $descriptions = $request->input('descriptions', []);

            $maxSequence = $gallery->items()->max('sequence') ?? -1;
            $uploaded = [];
            $uploadedPaths = [];

            foreach ($files as $index => $file) {
                $title = $titles[$index] ?? null;
                $description = $descriptions[$index] ?? null;

                $filename = time() . '_' . $index . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('gallery/items', $filename, 'public');
                $uploadedPaths[] = $path;

                $item = GalleryItem::create([
                    'gallery_id' => $gallery->id,
                    'type' => 'image',
                    'file_path' => $path,
                    'title' => $title,
                    'description' => $description,
                    'sequence' => $maxSequence + $index + 1,
                ]);

                $uploaded[] = new GalleryItemResource($item);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($uploaded) . ' items uploaded successfully',
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
                'gallery_id' => $galleryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload gallery items',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
