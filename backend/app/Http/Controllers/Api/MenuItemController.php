<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Requests\ReorderRequest;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class MenuItemController extends Controller
{
    /**
     * Display a listing of all menu items (admin).
     */
    public function index()
    {
        $menuItems = MenuItem::ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $menuItems,
            'message' => 'Menu items retrieved successfully'
        ]);
    }

    /**
     * Get active menu items (public endpoint for navbar).
     */
    public function publicMenuItems()
    {
        $menuItems = MenuItem::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $menuItems,
            'message' => 'Active menu items retrieved successfully'
        ]);
    }

    /**
     * Store a newly created menu item.
     */
    public function store(StoreMenuItemRequest $request)
    {
        try {
            DB::beginTransaction();

            $menuItemData = $request->validated();

            // Set default values
            $menuItemData['is_active'] = $menuItemData['is_active'] ?? true;
            $menuItemData['sequence'] = $menuItemData['sequence'] ?? MenuItem::max('sequence') + 1;

            $menuItem = MenuItem::create($menuItemData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Menu item created successfully',
                'data' => $menuItem,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create menu item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified menu item.
     */
    public function update(UpdateMenuItemRequest $request, $id)
    {
        $menuItem = MenuItem::find($id);

        if (!$menuItem) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found',
                'error' => 'The requested menu item does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->validated();
            $menuItem->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Menu item updated successfully',
                'data' => $menuItem->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update menu item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified menu item.
     */
    public function destroy($id)
    {
        $menuItem = MenuItem::find($id);

        if (!$menuItem) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found',
                'error' => 'The requested menu item does not exist.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $menuItem->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Menu item deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete menu item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk reorder menu items.
     */
    public function reorder(ReorderRequest $request)
    {
        try {
            DB::beginTransaction();

            $items = $request->validated()['items'];

            foreach ($items as $item) {
                MenuItem::where('id', $item['id'])
                    ->update(['sequence' => $item['sequence']]);
            }

            DB::commit();

            $menuItems = MenuItem::ordered()->get();

            return response()->json([
                'success' => true,
                'message' => 'Menu items reordered successfully',
                'data' => $menuItems,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder menu items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
