<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePageSectionRequest;
use App\Http\Requests\ReorderRequest;
use App\Models\PageSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PageSectionController extends Controller
{
    /**
     * Display a listing of page sections (admin).
     */
    public function index(Request $request)
    {
        $query = PageSection::query();

        // Filter by page type if provided
        if ($request->has('page')) {
            $query->forPage($request->query('page'));
        }

        $sections = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $sections,
            'message' => 'Page sections retrieved successfully'
        ]);
    }

    /**
     * Get active page sections (public endpoint for page rendering).
     */
    public function publicSections(Request $request)
    {
        $query = PageSection::active();

        // Filter by page type if provided
        if ($request->has('page')) {
            $query->forPage($request->query('page'));
        }

        $sections = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $sections,
            'message' => 'Active page sections retrieved successfully'
        ]);
    }

    /**
     * Update the specified page section (toggle active/inactive).
     */
    public function update(UpdatePageSectionRequest $request, $id)
    {
        $section = PageSection::find($id);

        if (!$section) {
            return response()->json([
                'success' => false,
                'message' => 'Page section not found',
                'error' => 'The requested page section does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->validated();
            $section->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Page section updated successfully',
                'data' => $section->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update page section',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk reorder page sections.
     */
    public function reorder(ReorderRequest $request)
    {
        try {
            DB::beginTransaction();

            $items = $request->validated()['items'];

            foreach ($items as $item) {
                PageSection::where('id', $item['id'])
                    ->update(['sequence' => $item['sequence']]);
            }

            DB::commit();

            $sections = PageSection::ordered()->get();

            return response()->json([
                'success' => true,
                'message' => 'Page sections reordered successfully',
                'data' => $sections,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder page sections',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
