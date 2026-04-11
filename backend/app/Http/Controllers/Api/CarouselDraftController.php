<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarouselDraftRequest;
use App\Http\Resources\CarouselDraftResource;
use App\Models\CarouselDraft;
use App\Models\CarouselSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarouselDraftController extends Controller
{
    public function saveDraft(StoreCarouselDraftRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $draft = CarouselDraft::create([
                'title' => $validated['title'],
                'source_url' => $validated['source_url'] ?? null,
                'shortcode' => $validated['shortcode'] ?? null,
                'source_images' => $validated['source_images'] ?? null,
                'source_analysis' => $validated['source_analysis'] ?? null,
                'creative_direction' => $validated['creative_direction'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'target_platform' => $validated['target_platform'] ?? 'instagram',
                'hook_category' => $validated['hook_category'] ?? null,
                'captions' => $validated['captions'] ?? null,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
            ]);

            foreach ($validated['slides'] as $slideData) {
                CarouselSlide::create([
                    'carousel_draft_id' => $draft->id,
                    'slide_index' => $slideData['slide_index'],
                    'slide_type' => $slideData['slide_type'],
                    'source_image_url' => $slideData['source_image_url'] ?? null,
                    'prompt' => $slideData['prompt'] ?? null,
                    'generated_image_url' => $slideData['generated_image_url'] ?? null,
                    'generation_uuid' => $slideData['generation_uuid'] ?? null,
                    'generation_status' => $slideData['generation_status'] ?? 'pending',
                    'wow_score' => $slideData['wow_score'] ?? null,
                ]);
            }

            $draft->load('slides');

            return response()->json([
                'success' => true,
                'data' => new CarouselDraftResource($draft),
                'message' => 'Carousel draft created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create carousel draft: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = CarouselDraft::query();

            if ($request->has('status')) {
                $query->byStatus($request->get('status'));
            }

            if ($request->has('target_platform')) {
                $query->byPlatform($request->get('target_platform'));
            }

            $perPage = $request->get('per_page', 15);
            $drafts = $query->with('slides')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => CarouselDraftResource::collection($drafts)->response()->getData(true),
                'message' => 'Carousel drafts retrieved successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve carousel drafts: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function show(CarouselDraft $carouselDraft): JsonResponse
    {
        try {
            $carouselDraft->load('slides');

            return response()->json([
                'success' => true,
                'data' => new CarouselDraftResource($carouselDraft),
                'message' => 'Carousel draft retrieved successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve carousel draft: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function approve(CarouselDraft $carouselDraft): JsonResponse
    {
        try {
            $carouselDraft->update([
                'status' => 'approved',
                'rejection_reason' => null,
            ]);

            $carouselDraft->load('slides');

            return response()->json([
                'success' => true,
                'data' => new CarouselDraftResource($carouselDraft),
                'message' => 'Carousel draft approved successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve carousel draft: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function reject(Request $request, CarouselDraft $carouselDraft): JsonResponse
    {
        try {
            $request->validate(['rejection_reason' => 'required|string']);

            $carouselDraft->update([
                'status' => 'draft',
                'rejection_reason' => $request->get('rejection_reason'),
            ]);

            $carouselDraft->load('slides');

            return response()->json([
                'success' => true,
                'data' => new CarouselDraftResource($carouselDraft),
                'message' => 'Carousel draft rejected successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject carousel draft: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function schedule(Request $request, CarouselDraft $carouselDraft): JsonResponse
    {
        try {
            $validated = $request->validate(['scheduled_at' => 'required|date']);

            if ($carouselDraft->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved drafts can be scheduled',
                ], 400);
            }

            $carouselDraft->update([
                'status' => 'scheduled',
                'scheduled_at' => $validated['scheduled_at'],
            ]);

            $carouselDraft->load('slides');

            return response()->json([
                'success' => true,
                'data' => new CarouselDraftResource($carouselDraft),
                'message' => 'Carousel draft scheduled successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule carousel draft: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function updateSlideStatus(Request $request, $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'generation_status' => 'required|in:pending,processing,completed,failed',
                'generated_image_url' => 'nullable|url',
                'error_message' => 'nullable|string',
                'wow_score' => 'nullable|array',
            ]);

            $slide = CarouselSlide::where('generation_uuid', $uuid)->firstOrFail();

            $slide->update([
                'generation_status' => $validated['generation_status'],
                'generated_image_url' => $validated['generated_image_url'] ?? $slide->generated_image_url,
                'error_message' => $validated['error_message'] ?? null,
                'wow_score' => $validated['wow_score'] ?? $slide->wow_score,
            ]);

            return response()->json([
                'success' => true,
                'data' => $slide,
                'message' => 'Slide status updated successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update slide status: ' . $e->getMessage(),
            ], 400);
        }
    }
}
