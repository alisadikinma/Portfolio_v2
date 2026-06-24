<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public FAQ endpoint (GEO Pillar 2). Serves the curated Q&A set from
 * config/faq.php — the SAME single source the SSR /faq page renders. The Vue
 * FaqView fetches this to render the human-facing accordion, so the answer copy
 * never duplicates or drifts from the crawlable SSR version.
 */
class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => config('faq.items', []),
        ]);
    }
}
