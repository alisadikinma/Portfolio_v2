<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAwardRequest;
use App\Http\Requests\UpdateAwardRequest;
use App\Models\Award;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AwardController extends Controller
{
    /**
     * Display a listing of awards for admin (with pagination).
     */
    public function indexForAdmin(Request $request)
    {
        $query = Award::with('galleries');

        // Search
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('organization', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('received_at', '>=', $request->query('date_from'));
        }
        if ($request->has('date_to')) {
            $query->where('received_at', '<=', $request->query('date_to'));
        }

        try {
            $query->orderBy('sort_order', 'asc')->orderBy('received_at', 'desc');
        } catch (\Exception $e) {
            $query->orderBy('received_at', 'desc');
        }

        $perPage = min($request->query('per_page', 10), 50);
        $awards = $query->paginate($perPage);

        $formattedAwards = $awards->map(function($award) {
            return [
                'id' => $award->id,
                'title' => $award->title,
                'description' => $award->description,
                'organization' => $award->organization,
                'credential_id' => $award->credential_id,
                'credential_url' => $award->credential_url,
                'image' => $award->image,
                'received_at' => $award->received_at->format('Y-m-d'),
                'sort_order' => $award->order ?? 0,
                'gallery_count' => $award->galleries->count(),
                'total_photos' => $award->total_photos,
                'featured_gallery_id' => $award->featured_gallery_id
            ];
        });

        return response()->json([
            'data' => $formattedAwards,
            'meta' => [
                'current_page' => $awards->currentPage(),
                'last_page' => $awards->lastPage(),
                'per_page' => $awards->perPage(),
                'total' => $awards->total(),
                'from' => $awards->firstItem(),
                'to' => $awards->lastItem(),
            ],
            'links' => [
                'first' => $awards->url(1),
                'last' => $awards->url($awards->lastPage()),
                'prev' => $awards->previousPageUrl(),
                'next' => $awards->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get all awards
     */
    public function index()
    {
        try {
            $awards = Award::with('galleries')
                           ->orderBy('sort_order', 'asc')
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
                'sort_order' => $award->order ?? 0,
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
                    'received_at' => $award->received_at->format('Y-m-d'),
                    'sort_order' => $award->order,
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
     * Store a newly created award.
     */
    public function store(StoreAwardRequest $request)
    {
        try {
            DB::beginTransaction();

            // Prepare data
            $awardData = $request->only([
                'title',
                'description',
                'organization',
                'credential_id',
                'credential_url',
                'received_at',
                'sort_order',
                'featured_gallery_id',
            ]);

            // Handle file upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/awards'), $filename);
                $awardData['image'] = '/uploads/awards/' . $filename;
            }

            $award = Award::create($awardData);

            DB::commit();

            $award->load('galleries');

            return response()->json([
                'success' => true,
                'message' => 'Award created successfully',
                'data' => [
                    'id' => $award->id,
                    'title' => $award->title,
                    'description' => $award->description,
                    'organization' => $award->organization,
                    'credential_id' => $award->credential_id,
                    'credential_url' => $award->credential_url,
                    'image' => $award->image,
                    'received_at' => $award->received_at->format('Y-m-d'),
                    'sort_order' => $award->order,
                    'gallery_count' => $award->galleries->count(),
                    'total_photos' => $award->total_photos,
                    'featured_gallery_id' => $award->featured_gallery_id
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create award',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified award.
     */
    public function update(UpdateAwardRequest $request, $id)
    {
        $award = Award::find($id);

        if (!$award) {
            return response()->json([
                'success' => false,
                'message' => 'Award not found',
                'error' => 'The requested award does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Prepare update data
            $updateData = $request->only([
                'title',
                'description',
                'organization',
                'credential_id',
                'credential_url',
                'received_at',
                'sort_order',
                'featured_gallery_id',
            ]);

            // Handle file upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($award->image && file_exists(public_path($award->image))) {
                    unlink(public_path($award->image));
                }

                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/awards'), $filename);
                $updateData['image'] = '/uploads/awards/' . $filename;
            }

            $award->update($updateData);

            DB::commit();

            $award->load('galleries');

            return response()->json([
                'success' => true,
                'message' => 'Award updated successfully',
                'data' => [
                    'id' => $award->id,
                    'title' => $award->title,
                    'description' => $award->description,
                    'organization' => $award->organization,
                    'credential_id' => $award->credential_id,
                    'credential_url' => $award->credential_url,
                    'image' => $award->image,
                    'received_at' => $award->received_at->format('Y-m-d'),
                    'sort_order' => $award->order,
                    'gallery_count' => $award->galleries->count(),
                    'total_photos' => $award->total_photos,
                    'featured_gallery_id' => $award->featured_gallery_id
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update award',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified award.
     */
    public function destroy($id)
    {
        $award = Award::find($id);

        if (!$award) {
            return response()->json([
                'success' => false,
                'message' => 'Award not found',
                'error' => 'The requested award does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Delete image if exists
            if ($award->image && file_exists(public_path($award->image))) {
                unlink(public_path($award->image));
            }

            // Detach all galleries
            $award->galleries()->detach();

            $award->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Award deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete award',
                'error' => $e->getMessage(),
            ], 500);
        }
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
