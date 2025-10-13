<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

        $query = Project::with(['translations'])
            ->where('published', true);

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->query('category'));
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->where('featured', (bool) $request->query('featured'));
        }

        // Search
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhereHas('translations', function ($tq) use ($searchTerm) {
                      $tq->where('title', 'like', "%{$searchTerm}%")
                         ->orWhere('description', 'like', "%{$searchTerm}%");
                  });
            });
        }

        $query->orderBy('order', 'asc')
              ->orderBy('created_at', 'desc');

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
            ->where('published', true)
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
     * Store a newly created project.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'description' => ['required', 'string'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'category' => ['required', 'string', Rule::in(['web', 'mobile', 'ai', 'iot', 'automation'])],
            'technologies' => ['nullable', 'array'],
            'client' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'completed_at' => ['nullable', 'date'],
            'featured' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],

            // Translations
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.language' => ['required', 'string', Rule::in(['en', 'id'])],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.content' => ['nullable', 'string'],
            'translations.*.meta_title' => ['nullable', 'string', 'max:255'],
            'translations.*.meta_description' => ['nullable', 'string', 'max:500'],
            'translations.*.og_title' => ['nullable', 'string', 'max:255'],
            'translations.*.og_description' => ['nullable', 'string', 'max:500'],
            'translations.*.canonical_url' => ['nullable', 'url', 'max:255'],
            'translations.*.ai_summary' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $project = Project::create([
                'title' => $request->input('title'),
                'slug' => $request->input('slug'),
                'description' => $request->input('description'),
                'content' => $request->input('content'),
                'image' => $request->input('image'),
                'images' => $request->input('images'),
                'category' => $request->input('category'),
                'technologies' => $request->input('technologies'),
                'client' => $request->input('client'),
                'url' => $request->input('url'),
                'completed_at' => $request->input('completed_at'),
                'featured' => $request->input('featured', false),
                'published' => $request->input('published', true),
                'order' => $request->input('order', 0),
            ]);

            foreach ($request->input('translations', []) as $translation) {
                $project->translations()->create($translation);
            }

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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Project not found',
                'error' => 'The requested project does not exist.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($id)],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'category' => ['nullable', 'string', Rule::in(['web', 'mobile', 'ai', 'iot', 'automation'])],
            'technologies' => ['nullable', 'array'],
            'client' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'completed_at' => ['nullable', 'date'],
            'featured' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],

            // Translations
            'translations' => ['nullable', 'array'],
            'translations.*.id' => ['nullable', 'integer', 'exists:project_translations,id'],
            'translations.*.language' => ['required', 'string', Rule::in(['en', 'id'])],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.content' => ['nullable', 'string'],
            'translations.*.meta_title' => ['nullable', 'string', 'max:255'],
            'translations.*.meta_description' => ['nullable', 'string', 'max:500'],
            'translations.*.og_title' => ['nullable', 'string', 'max:255'],
            'translations.*.og_description' => ['nullable', 'string', 'max:500'],
            'translations.*.canonical_url' => ['nullable', 'url', 'max:255'],
            'translations.*.ai_summary' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $project->update($request->only([
                'title',
                'slug',
                'description',
                'content',
                'image',
                'images',
                'category',
                'technologies',
                'client',
                'url',
                'completed_at',
                'featured',
                'published',
                'order',
            ]));

            if ($request->has('translations')) {
                foreach ($request->input('translations', []) as $translation) {
                    if (isset($translation['id'])) {
                        $project->translations()->where('id', $translation['id'])->update($translation);
                    } else {
                        $project->translations()->create($translation);
                    }
                }
            }

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
