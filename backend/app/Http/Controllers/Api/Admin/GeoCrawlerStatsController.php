<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Read-only AI-crawler stats (Pillar 5 measurement). Surfaces the last 30 days
 * of geo_crawler_hits grouped by bot — the AI BOT crawls GA4 cannot observe.
 */
class GeoCrawlerStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('geo_crawler_hits')
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->groupBy('bot')
            ->orderByDesc(DB::raw('SUM(hits)'))
            ->get([
                'bot',
                DB::raw('SUM(hits) as total'),
                DB::raw('MAX(date) as last_seen'),
            ]);

        $data = $rows->map(fn ($row) => [
            'bot' => $row->bot,
            'total' => (int) $row->total,
            'last_seen' => $row->last_seen,
        ])->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
