<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * SettingController
 *
 * Handles retrieval of site settings
 */
class SettingController extends Controller
{
    /**
     * Display a listing of all settings grouped by group.
     */
    public function index(): JsonResponse
    {
        $settings = Setting::all()->groupBy('group');

        $transformed = $settings->map(function ($group) {
            return SettingResource::collection($group);
        });

        return response()->json([
            'data' => $transformed,
        ]);
    }

    /**
     * Display settings by group.
     */
    public function getByGroup(string $group): JsonResponse
    {
        $settings = Setting::byGroup($group)->get();

        return response()->json([
            'data' => SettingResource::collection($settings),
        ]);
    }
}
