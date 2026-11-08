<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public FAQ endpoint (GEO Pillar 2). Serves the curated Q&A set from
 * config/faq.php — the SAME single source the SSR /faq page renders. The Vue
 * FaqView fetches this to render the human-facing accordion, so the answer copy
 * never duplicates or drifts from the crawlable SSR version.
 */
class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // ?lang=id serves the Indonesian mirror; anything else (or none) stays English,
        // so the legacy Vue FaqView keeps its current behaviour.
        $items = $request->query('lang') === 'id'
            ? (config('faq.items_id') ?: config('faq.items', []))
            : config('faq.items', []);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
