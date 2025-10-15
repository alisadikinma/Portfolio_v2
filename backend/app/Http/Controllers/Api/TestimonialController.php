<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTestimonialRequest;
use App\Http\Requests\UpdateTestimonialRequest;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * TestimonialController
 *
 * Handles CRUD operations for testimonials
 */
class TestimonialController extends Controller
{
    /**
     * Display a listing of active testimonials (public).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Testimonial::active()->ordered();

        $perPage = min($request->query('per_page', 15), 50);
        $testimonials = $query->paginate($perPage);

        return response()->json([
            'data' => TestimonialResource::collection($testimonials),
            'meta' => [
                'current_page' => $testimonials->currentPage(),
                'last_page' => $testimonials->lastPage(),
                'per_page' => $testimonials->perPage(),
                'total' => $testimonials->total(),
                'from' => $testimonials->firstItem(),
                'to' => $testimonials->lastItem(),
            ],
            'links' => [
                'first' => $testimonials->url(1),
                'last' => $testimonials->url($testimonials->lastPage()),
                'prev' => $testimonials->previousPageUrl(),
                'next' => $testimonials->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Display a listing of all testimonials for admin (including inactive).
     */
    public function indexForAdmin(Request $request): JsonResponse
    {
        $query = Testimonial::query();

        // Search
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('client_name', 'like', "%{$searchTerm}%")
                  ->orWhere('company_name', 'like', "%{$searchTerm}%")
                  ->orWhere('job_title', 'like', "%{$searchTerm}%")
                  ->orWhere('testimonial_text', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by rating
        if ($request->has('rating')) {
            $query->where('star_rating', $request->query('rating'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }

        $query->orderBy('sort_order')->orderBy('created_at', 'desc');

        $perPage = min($request->query('per_page', 10), 50);
        $testimonials = $query->paginate($perPage);

        return response()->json([
            'data' => TestimonialResource::collection($testimonials),
            'meta' => [
                'current_page' => $testimonials->currentPage(),
                'last_page' => $testimonials->lastPage(),
                'per_page' => $testimonials->perPage(),
                'total' => $testimonials->total(),
                'from' => $testimonials->firstItem(),
                'to' => $testimonials->lastItem(),
            ],
            'links' => [
                'first' => $testimonials->url(1),
                'last' => $testimonials->url($testimonials->lastPage()),
                'prev' => $testimonials->previousPageUrl(),
                'next' => $testimonials->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Display the specified testimonial.
     */
    public function show(int $id): JsonResponse
    {
        $testimonial = Testimonial::findOrFail($id);

        return response()->json([
            'data' => new TestimonialResource($testimonial),
        ]);
    }

    /**
     * Store a newly created testimonial.
     */
    public function store(StoreTestimonialRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Prepare data
            $testimonialData = $request->only([
                'client_name',
                'company_name',
                'job_title',
                'testimonial_text',
                'star_rating',
                'is_active',
                'sort_order',
            ]);

            // Handle file upload
            if ($request->hasFile('client_photo')) {
                $file = $request->file('client_photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/testimonials'), $filename);
                $testimonialData['client_photo'] = '/uploads/testimonials/' . $filename;
            }

            $testimonial = Testimonial::create($testimonialData);

            DB::commit();

            return response()->json([
                'message' => 'Testimonial created successfully',
                'data' => new TestimonialResource($testimonial),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create testimonial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified testimonial.
     */
    public function update(UpdateTestimonialRequest $request, int $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return response()->json([
                'message' => 'Testimonial not found',
                'error' => 'The requested testimonial does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Prepare update data
            $updateData = $request->only([
                'client_name',
                'company_name',
                'job_title',
                'testimonial_text',
                'star_rating',
                'is_active',
                'sort_order',
            ]);

            // Handle file upload
            if ($request->hasFile('client_photo')) {
                // Delete old photo if exists
                if ($testimonial->client_photo && file_exists(public_path($testimonial->client_photo))) {
                    unlink(public_path($testimonial->client_photo));
                }

                $file = $request->file('client_photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/testimonials'), $filename);
                $updateData['client_photo'] = '/uploads/testimonials/' . $filename;
            }

            $testimonial->update($updateData);

            DB::commit();

            return response()->json([
                'message' => 'Testimonial updated successfully',
                'data' => new TestimonialResource($testimonial),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update testimonial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified testimonial.
     */
    public function destroy(int $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return response()->json([
                'message' => 'Testimonial not found',
                'error' => 'The requested testimonial does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Delete photo if exists
            if ($testimonial->client_photo && file_exists(public_path($testimonial->client_photo))) {
                unlink(public_path($testimonial->client_photo));
            }

            $testimonial->delete();

            DB::commit();

            return response()->json([
                'message' => 'Testimonial deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete testimonial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
