<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ActivityFeedController extends Controller
{
    public function index()
    {
        $feed = Cache::remember('activity-feed', 300, function () {
            $posts = DB::table('posts')
                ->select(DB::raw("'post' as type, title, created_at"))
                ->where('published', true)
                ->orderByDesc('created_at')
                ->limit(10);

            $projects = DB::table('projects')
                ->select(DB::raw("'project' as type, title, created_at"))
                ->orderByDesc('created_at')
                ->limit(10);

            $awards = DB::table('awards')
                ->select(DB::raw("'award' as type, title, created_at"))
                ->orderByDesc('created_at')
                ->limit(10);

            $activities = $posts
                ->unionAll($projects)
                ->unionAll($awards)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $item->time_ago = \Carbon\Carbon::parse($item->created_at)->diffForHumans();
                    return $item;
                });

            return $activities;
        });

        return response()->json([
            'success' => true,
            'data' => $feed,
        ]);
    }
}
