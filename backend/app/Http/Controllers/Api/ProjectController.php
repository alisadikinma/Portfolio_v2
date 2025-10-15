<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * ProjectController
 *
 * Handles CRUD operations for projects with full i18n support
 */
class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $language = $request->query('lang', $request->header('Accept-Language', 'en'));
        $language = strtolower(substr($language, 0, 2));

        $query = Project::with(['translations']);

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->query('status'));
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->where('is_featured', (bool) $request->query('featured'));
        }

        // Search
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('client_name', 'like', "%{$searchTerm}%")
                  ->orWhereJsonContains('technologies', $searchTerm);
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min($request->query('per_page', 15), 50);
        $projects = $query->paginate($perPage);

        return response()->json([
            'data' => ProjectResource::collection($projects)->additional(['lang' => $language]),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ],
            'links' => [
                'first' => $projects->url(1),
                'last' => $projects->url($projects->lastPage()),
                'prev' => $projects->previousPageUrl(),
                'next' => $projects->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Display a listing of all projects for admin (including unpublished).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexForAdmin(Request $request): JsonResponse
    {
        $query = Project::with(['translations']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->where('is_featured', (bool) $request->query('featured'));
        }

        // Search
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('client_name', 'like', "%{$searchTerm}%")
                  ->orWhereJsonContains('technologies', $searchTerm);
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min($request->query('per_page', 10), 50);
        $projects = $query->paginate($perPage);

        return response()->json([
            'data' => ProjectResource::collection($projects),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ],
            'links' => [
                'first' => $projects->url(1),
                'last' => $projects->url($projects->lastPage()),
                'prev' => $projects->previousPageUrl(),
                'next' => $projects->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Display the specified project by slug.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $language = $request->query('lang', $request->header('Accept-Language', 'en'));
        $language = strtolower(substr($language, 0, 2));

        $project = Project::with(['translations'])
            ->where('slug', $slug)
            ->first();

        if (!$project) {
            return response()->json([
                'message' => 'Project not found',
                'error' => 'The requested project does not exist or is not published.',
            ], 404);
        }

        return response()->json([
            'data' => (new ProjectResource($project))->additional(['lang' => $language]),
        ]);
    }

    /**
     * Display the specified project by ID (for admin).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showById(int $id): JsonResponse
    {
        $project = Project::with(['translations'])->find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Project not found',
                'error' => 'The requested project does not exist.',
            ], 404);
        }

        return response()->json([
            'message' => 'Project retrieved successfully',
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * Store a newly created project.
     *
     * @param  \App\Http\Requests\StoreProjectRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Prepare data
            $projectData = $request->only([
                'title',
                'slug',
                'description',
                'technologies',
                'client_name',
                'project_url',
                'github_url',
                'status',
                'start_date',
                'end_date',
                'is_featured',
                'meta_title',
                'meta_description',
                'focus_keyword',
                'canonical_url',
            ]);

            // Handle file upload
            if ($request->hasFile('featured_image')) {
                $file = $request->file('featured_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/projects'), $filename);
                $projectData['featured_image'] = '/uploads/projects/' . $filename;
            }

            $project = Project::create($projectData);

            DB::commit();

            $project->load(['translations']);

            return response()->json([
                'message' => 'Project created successfully',
                'data' => new ProjectResource($project),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified project.
     *
     * @param  \App\Http\Requests\UpdateProjectRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProjectRequest $request, int $id): JsonResponse
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Project not found',
                'error' => 'The requested project does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Prepare update data
            $updateData = $request->only([
                'title',
                'slug',
                'description',
                'technologies',
                'client_name',
                'project_url',
                'github_url',
                'status',
                'start_date',
                'end_date',
                'is_featured',
                'meta_title',
                'meta_description',
                'focus_keyword',
                'canonical_url',
            ]);

            // Handle file upload
            if ($request->hasFile('featured_image')) {
                $file = $request->file('featured_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/projects'), $filename);
                $updateData['featured_image'] = '/uploads/projects/' . $filename;
            }

            $project->update($updateData);

            DB::commit();

            $project->load(['translations']);

            return response()->json([
                'message' => 'Project updated successfully',
                'data' => new ProjectResource($project),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified project.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Project not found',
                'error' => 'The requested project does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $project->translations()->delete();
            $project->delete();

            DB::commit();

            return response()->json([
                'message' => 'Project deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
