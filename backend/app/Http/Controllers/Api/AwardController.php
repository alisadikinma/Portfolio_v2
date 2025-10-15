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
        try {
            $awards = Award::with('galleries')
                           ->orderBy('order', 'asc')
                           ->orderBy('received_at', 'desc')
                           ->get();
        } catch (\Exception $e) {
            $awards = Award::with('galleries')
                           ->orderBy('received_at', 'desc')
                           ->get();
        }

        $formattedAwards = $awards->map(function($award) {
            return [
                'id' => $award->id,
                'award_title' => $award->title,
                'title' => $award->title,
                'description' => $award->description,
                'issuing_organization' => $award->organization,
                'organization' => $award->organization,
                'credential_id' => $award->credential_id,
                'credential_url' => $award->credential_url,
                'image' => $award->image,
                'award_date' => $award->received_at,
                'received_at' => $award->received_at,
                'order' => $award->order ?? 0,
                'gallery_count' => $award->galleries->count(),
                'total_photos' => $award->total_photos
            ];
        });

        // Return with proper structure for frontend
        return response()->json([
            'success' => true,
            'data' => $formattedAwards,
            'message' => 'Awards retrieved successfully'
        ]);
    }

    /**
     * Get single award with galleries
     */
    public function show($id)
    {
        $award = Award::with(['galleries', 'featuredGallery'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'award' => [
                    'id' => $award->id,
                    'title' => $award->title,
                    'description' => $award->description,
                    'organization' => $award->organization,
                    'credential_id' => $award->credential_id,
                    'credential_url' => $award->credential_url,
                    'image' => $award->image,
                    'received_at' => $award->received_at,
                    'order' => $award->order,
                    'featured_gallery_id' => $award->featured_gallery_id,
                    'total_photos' => $award->total_photos
                ],
                'galleries' => $award->galleries->map(function($gallery) {
                    return [
                        'id' => $gallery->id,
                        'title' => $gallery->title,
                        'description' => $gallery->description,
                        'category' => $gallery->category,
                        'image' => $gallery->image,
                        'sort_order' => $gallery->pivot->sort_order,
                        'is_active' => $gallery->is_active
                    ];
                })
            ],
            'message' => 'Award retrieved successfully'
        ]);
    }

    /**
     * Get award's galleries
     */
    public function getGalleries($id)
    {
        $award = Award::with('galleries')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
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
                        'image' => $gallery->image,
                        'description' => $gallery->description,
                        'sort_order' => $gallery->pivot->sort_order,
                        'is_active' => $gallery->is_active
                    ];
                }),
                'total_photos' => $award->total_photos
            ],
            'message' => 'Galleries retrieved successfully'
        ]);
    }

    /**
     * Link gallery to award
     */
    public function linkGallery(Request $request, $id)
    {
        $validated = $request->validate([
            'gallery_id' => 'required|exists:galleries,id',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        $award = Award::findOrFail($id);
        
        // Check if already linked
        if ($award->galleries()->where('gallery_id', $validated['gallery_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery already linked to this award'
            ], 409);
        }

        $award->galleries()->attach($validated['gallery_id'], [
            'sort_order' => $validated['sort_order'] ?? 0
        ]);

        return response()->json([
            'success' => true,
            'data' => $award->load('galleries'),
            'message' => 'Gallery linked successfully'
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
                'success' => false,
                'message' => 'Gallery not linked to this award'
            ], 404);
        }

        $award->galleries()->detach($galleryId);

        return response()->json([
            'success' => true,
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
            'galleries.*.sort_order' => 'required|integer|min:0'
        ]);

        $award = Award::findOrFail($id);

        foreach ($validated['galleries'] as $gallery) {
            $award->galleries()->updateExistingPivot($gallery['id'], [
                'sort_order' => $gallery['sort_order']
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $award->fresh()->load('galleries'),
            'message' => 'Galleries reordered successfully'
        ]);
    }
}
