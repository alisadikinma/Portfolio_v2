<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeminiGenCircuitBreaker;
use Illuminate\Http\JsonResponse;

class GeminigenStatusController extends Controller
{
    public function __invoke(GeminiGenCircuitBreaker $breaker): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'state' => $breaker->state(),
                'opened_at' => $breaker->openedAt()?->toIso8601String(),
                'next_probe_at' => $breaker->nextProbeAt()?->toIso8601String(),
                'last_probe_result' => $breaker->lastProbeResult(),
                'failure_count_in_window' => $breaker->failureCountInWindow(),
            ],
        ]);
    }
}
