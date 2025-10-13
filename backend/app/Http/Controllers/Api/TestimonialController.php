<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * TestimonialController
 *
 * Handles CRUD operations for testimonials
 */
class TestimonialController extends Controller
{
    /**
     * Display a listing of active testimonials.
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
     * Display the specified testimonial.
     */
    public function show(int $id): JsonResponse
    {
        $testimonial = Testimonial::findOrFail($id);

        return response()->json([
            'data' => new TestimonialResource($testimonial),
        ]);
    }
}
