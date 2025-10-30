<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    /**
     * Display a listing of services.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->query('active'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Order by
        $orderBy = $request->query('order_by', 'order');
        $orderDir = $request->query('order_dir', 'asc');
        $query->orderBy($orderBy, $orderDir);

        // Check if pagination is requested
        if ($request->has('per_page') || !$request->has('all')) {
            $perPage = $request->query('per_page', 12);
            $services = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ServiceResource::collection($services),
                'links' => [
                    'first' => $services->url(1),
                    'last' => $services->url($services->lastPage()),
                    'prev' => $services->previousPageUrl(),
                    'next' => $services->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $services->currentPage(),
                    'from' => $services->firstItem(),
                    'last_page' => $services->lastPage(),
                    'per_page' => $services->perPage(),
                    'to' => $services->lastItem(),
                    'total' => $services->total(),
                ],
            ]);
        }

        // Return all services without pagination
        $services = $query->get();

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services),
        ]);
    }

    /**
     * Display the specified service.
     */
    public function show(string $slug): JsonResponse
    {
        $service = Service::where('slug', $slug)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new ServiceResource($service),
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Set order if not provided
            if (!isset($data['order'])) {
                $maxOrder = Service::max('order') ?? 0;
                $data['order'] = $maxOrder + 1;
            }

            // Set active default
            $data['active'] = $data['active'] ?? true;

            $service = Service::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Service created successfully',
                'data' => new ServiceResource($service),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create service', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create service',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Update the specified service.
     */
    public function update(UpdateServiceRequest $request, string $slug): JsonResponse
    {
        $service = Service::where('slug', $slug)->firstOrFail();

        try {
            DB::beginTransaction();

            $data = $request->validated();
            $service->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Service updated successfully',
                'data' => new ServiceResource($service),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update service', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update service',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Remove the specified service.
     */
    public function destroy(string $slug): JsonResponse
    {
        try {
            $service = Service::where('slug', $slug)->firstOrFail();

            DB::beginTransaction();

            $service->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete service', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
