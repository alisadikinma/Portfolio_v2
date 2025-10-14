<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Award;
use App\Models\Gallery;
use Illuminate\Http\Request;

class AwardController extends Controller
{
    /**
     * Get all awards
     */
    public function index()
    {
        // Check if order column exists, otherwise order by received_at
        try {
            $awards = Award::with('galleries')
                           ->orderBy('order', 'asc')
                           ->orderBy('received_at', 'desc')
                           ->get();
        } catch (\Exception $e) {
            // Fallback if order column doesn't exist
            $awards = Award::with('galleries')
                           ->orderBy('received_at', 'desc')
                           ->get();
        }

        $awards = $awards->map(function($award) {
            return [
                'id' => $award->id,
                'award_title' => $award->title, // Match frontend expectation
                'title' => $award->title, // Keep for backward compatibility
                'description' => $award->description,
                'issuing_organization' => $award->organization, // Match frontend expectation
                'organization' => $award->organization, // Keep for backward compatibility
                'credential_id' => $award->credential_id,
                'credential_url' => $award->credential_url,
                'image' => $award->image,
                'award_date' => $award->received_at, // Match frontend expectation
                'received_at' => $award->received_at, // Keep for backward compatibility
                'order' => $award->order ?? 0,
                'gallery_count' => $award->galleries->count(),
                'total_photos' => $award->total_photos
            ];
        });

        return response()->json($awards);
    }

    /**
     * Get single award with galleries
     */
    public function show($id)
    {
        $award = Award::with(['galleries.images', 'featuredGallery'])->findOrFail($id);

        return response()->json([
            'award' => $award,
            'galleries' => $award->galleries->map(function($gallery) {
                return [
                    'id' => $gallery->id,
                    'title' => $gallery->title,
                    'description' => $gallery->description,
                    'category' => $gallery->category,
                    'image' => $gallery->image,
                    'imageCount' => $gallery->images->count(),
                    'images' => $gallery->images,
                    'display_order' => $gallery->pivot->display_order
                ];
            }),
            'total_photos' => $award->total_photos
        ]);
    }

    /**
     * Get award's galleries
     */
    public function getGalleries($id)
    {
        $award = Award::with(['galleries.images'])->findOrFail($id);
        
        return response()->json([
            'award' => [
                'id' => $award->id,
                'title' => $award->title,
                'organization' => $award->organization
            ],
            'galleries' => $award->galleries->map(function($gallery) {
                return [
                    'id' => $gallery->id,
                    'title' => $gallery->title,
                    'category' => $gallery->category,
                    'thumbnail' => $gallery->image,
                    'imageCount' => $gallery->images->count(),
                    'images' => $gallery->images->map(function($image) {
                        return [
                            'id' => $image->id,
                            'url' => $image->image,
                            'caption' => $image->caption,
                            'order' => $image->order
                        ];
                    }),
                    'display_order' => $gallery->pivot->display_order
                ];
            }),
            'totalPhotos' => $award->total_photos
        ]);
    }

    /**
     * Link gallery to award
     */
    public function linkGallery(Request $request, $id)
    {
        $validated = $request->validate([
            'gallery_id' => 'required|exists:galleries,id',
            'display_order' => 'nullable|integer|min:0'
        ]);

        $award = Award::findOrFail($id);
        
        // Check if already linked
        if ($award->galleries()->where('gallery_id', $validated['gallery_id'])->exists()) {
            return response()->json([
                'message' => 'Gallery already linked to this award'
            ], 409);
        }

        $award->galleries()->attach($validated['gallery_id'], [
            'display_order' => $validated['display_order'] ?? 0
        ]);

        return response()->json([
            'message' => 'Gallery linked successfully',
            'award' => $award->load('galleries')
        ], 201);
    }

    /**
     * Unlink gallery from award
     */
    public function unlinkGallery($id, $galleryId)
    {
        $award = Award::findOrFail($id);
        
        if (!$award->galleries()->where('gallery_id', $galleryId)->exists()) {
            return response()->json([
                'message' => 'Gallery not linked to this award'
            ], 404);
        }

        $award->galleries()->detach($galleryId);

        return response()->json([
            'message' => 'Gallery unlinked successfully'
        ]);
    }

    /**
     * Reorder galleries for award
     */
    public function reorderGalleries(Request $request, $id)
    {
        $validated = $request->validate([
            'galleries' => 'required|array',
            'galleries.*.id' => 'required|exists:galleries,id',
            'galleries.*.display_order' => 'required|integer|min:0'
        ]);

        $award = Award::findOrFail($id);

        foreach ($validated['galleries'] as $gallery) {
            $award->galleries()->updateExistingPivot($gallery['id'], [
                'display_order' => $gallery['display_order']
            ]);
        }

        return response()->json([
            'message' => 'Galleries reordered successfully',
            'galleries' => $award->fresh()->galleries
        ]);
    }
}
