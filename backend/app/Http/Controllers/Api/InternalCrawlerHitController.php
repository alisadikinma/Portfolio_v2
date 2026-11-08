<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CrawlerHitRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/internal/crawler-hit — Astro reports an AI-bot crawl.
 * Auth: shared secret in X-Internal-Token (services.internal.token).
 */
class InternalCrawlerHitController extends Controller
{
    public function __invoke(Request $request, CrawlerHitRecorder $recorder): JsonResponse
    {
        $expected = config('services.internal.token');

        if (! is_string($expected) || $expected === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_TOKEN_NOT_CONFIGURED',
                    'message' => 'Internal API token is not configured.',
                ],
            ], 503);
        }

        $provided = $request->header('X-Internal-Token');

        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Invalid internal token.'],
            ], 401);
        }

        $bot = $request->input('bot');
        $known = array_values((array) config('ai_bots.map', []));

        if (! is_string($bot) || ! in_array($bot, $known, true)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNKNOWN_BOT', 'message' => 'Unknown bot.'],
            ], 422);
        }

        // Fail-open: recorder logs and swallows DB errors; still 202.
        $recorder->record($bot);

        return response()->json(['success' => true], 202);
    }
}
