<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostingTimeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Admin endpoint for posting time rules.
 *
 * Per docs/plans/2026-05-06-linkedin-calendar-and-ai-time-rules.md §6.1.
 *
 *   GET  /api/admin/posting-rules?platform=linkedin
 *        -> rules + day_summaries + sources + last_researched_at
 *   POST /api/admin/posting-rules/refresh  body: {platform: linkedin}
 *        -> dispatches `posting-rules:research --platform=...` via Artisan::queue
 */
class PostingRuleController extends Controller
{
    private const VALID_PLATFORMS = ['linkedin', 'instagram', 'tiktok', 'youtube_shorts'];

    public function index(Request $request): JsonResponse
    {
        $platform = (string) $request->query('platform', 'linkedin');
        $audience = (string) $request->query('audience', 'b2b_tech');

        if (!in_array($platform, self::VALID_PLATFORMS, true)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PLATFORM',
                    'message' => "Invalid platform '{$platform}'. Must be one of: " . implode(', ', self::VALID_PLATFORMS),
                ],
            ], 422);
        }

        $rules = PostingTimeRule::query()
            ->forPlatform($platform)
            ->forAudience($audience)
            ->orderBy('day_of_week')
            ->orderBy('hour')
            ->get();

        $daySummaries = $this->computeDaySummaries($rules);

        $latestResearchedAt = $rules->max('last_researched_at');
        $sources = $this->extractUniqueSources($rules);

        return response()->json([
            'success' => true,
            'data' => [
                'platform' => $platform,
                'audience' => $audience,
                'rules' => $rules->map(fn ($r) => [
                    'day_of_week' => $r->day_of_week,
                    'hour' => $r->hour,
                    'score' => $r->score,
                    'note' => $r->notes,
                ])->values(),
                'day_summaries' => $daySummaries,
                'sources' => $sources,
                'last_researched_at' => $latestResearchedAt instanceof Carbon
                    ? $latestResearchedAt->toIso8601String()
                    : null,
                'rule_count' => $rules->count(),
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', Rule::in(self::VALID_PLATFORMS)],
            'audience' => ['nullable', 'string', 'max:64'],
        ]);

        $platform = $validated['platform'];
        $audience = $validated['audience'] ?? 'b2b_tech';

        // Queue dispatch — research takes ~30-60s and we don't block the HTTP
        // request. Artisan::queue uses Laravel's queue connection so a worker
        // (portfolio-queue.service per CLAUDE.md) picks it up.
        Artisan::queue('posting-rules:research', [
            '--platform' => $platform,
            '--audience' => $audience,
            '--force' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'dispatched_at' => Carbon::now()->toIso8601String(),
                'platform' => $platform,
                'audience' => $audience,
                'eta_seconds' => 60,
            ],
            'message' => 'Research dispatched. Polls /api/admin/posting-rules?platform=... for the updated last_researched_at.',
        ]);
    }

    /**
     * For each day_of_week present in the rules, surface:
     *   - best_score: max score that day
     *   - best_hours: top 3 hours where score >= 80, ordered by score DESC then hour ASC
     */
    private function computeDaySummaries($rules): array
    {
        if ($rules->isEmpty()) {
            return [];
        }

        $byDow = $rules->groupBy('day_of_week');

        return $byDow->map(function ($dayRules, $dow) {
            $sorted = $dayRules->sortBy([
                ['score', 'desc'],
                ['hour', 'asc'],
            ]);
            $bestHours = $sorted
                ->where('score', '>=', 80)
                ->take(3)
                ->pluck('hour')
                ->values()
                ->all();

            return [
                'day_of_week' => (int) $dow,
                'best_score' => (int) $dayRules->max('score'),
                'best_hours' => $bestHours,
            ];
        })->values()->all();
    }

    private function extractUniqueSources($rules): array
    {
        $seen = [];
        $out = [];
        foreach ($rules as $r) {
            $url = (string) ($r->source_url ?? '');
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $out[] = [
                'url' => $url,
                'title' => (string) ($r->source_title ?? ''),
            ];
        }
        return $out;
    }
}
