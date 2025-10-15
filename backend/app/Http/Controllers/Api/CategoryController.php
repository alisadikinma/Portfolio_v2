<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * CategoryController
 *
 * Handles CRUD operations for categories
 */
class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::withCount(['posts' => function ($query) {
            $query->published();
        }])->orderBy('sort_order', 'asc')->get();

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * Display a listing of all categories for admin.
     */
    public function indexForAdmin(Request $request): JsonResponse
    {
        $query = Category::withCount('posts');

        // Search
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Sort
        $sortBy = $request->query('sort_by', 'sort_order');
        $sortOrder = $request->query('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->query('per_page', 20), 50);
        $categories = $query->paginate($perPage);

        return response()->json([
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ],
            'links' => [
                'first' => $categories->url(1),
                'last' => $categories->url($categories->lastPage()),
                'prev' => $categories->previousPageUrl(),
                'next' => $categories->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Display the specified category.
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->withCount(['posts' => function ($query) {
                $query->published();
            }])
            ->first();

        if (!$category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Display the specified category by ID (for admin).
     */
    public function showById(int $id): JsonResponse
    {
        $category = Category::withCount('posts')->find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found',
                'error' => 'The requested category does not exist.',
            ], 404);
        }

        return response()->json([
            'message' => 'Category retrieved successfully',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $category = Category::create($request->only([
                'name',
                'slug',
                'description',
                'color',
                'sort_order',
            ]));

            DB::commit();

            $category->loadCount('posts');

            return response()->json([
                'message' => 'Category created successfully',
                'data' => new CategoryResource($category),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found',
                'error' => 'The requested category does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $category->update($request->only([
                'name',
                'slug',
                'description',
                'color',
                'sort_order',
            ]));

            DB::commit();

            $category->loadCount('posts');

            return response()->json([
                'message' => 'Category updated successfully',
                'data' => new CategoryResource($category),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified category.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::withCount('posts')->find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found',
                'error' => 'The requested category does not exist.',
            ], 404);
        }

        // Check if category has posts
        if ($category->posts_count > 0) {
            return response()->json([
                'message' => 'Cannot delete category',
                'error' => 'This category has ' . $category->posts_count . ' post(s). Please reassign or delete the posts first.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $category->delete();

            DB::commit();

            return response()->json([
                'message' => 'Category deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
