<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function getByGroup($group)
    {
        $settings = Setting::byGroup($group)->get();

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }
}
