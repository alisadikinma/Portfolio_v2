<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Topic virality pre-scoring for the trending pipeline.
 *
 * Composite score combines two signals:
 *   - Momentum (mechanical, 0-100) — source authority + recency + cross-publisher coverage.
 *   - Virality (batch AI, 0-100) — Sonnet evaluates 5 triggers per Jonah Berger's STEPPS.
 *
 * Phase A.2 lands the momentum formula. Phase A.3 adds scoreViralityBatch(),
 * Phase A.4 adds scoreBatch() composite + cache.
 */
class TopicScoringService
{
    private const SOURCE_WEIGHTS = [
        'google_news' => 30,
        'google_trends' => 35,
        'tiktok' => 40,
        'youtube' => 35,
    ];

    private const SOURCE_DEFAULT_WEIGHT = 25;

    private const TIER_BONUS = [
        1 => 20,
        2 => 10,
        3 => 0,
    ];

    /**
     * Mechanical momentum score 0-100.
     *
     * Formula: base(source) + tierBonus + recencyBonus + publisherCountBonus, clamped [0,100].
     *
     * @param array{source?:string,publisher_tier?:int|null,pub_date?:string|null,publisher_count?:int|null} $topic
     */
    public function computeMomentum(array $topic): int
    {
        $base = self::SOURCE_WEIGHTS[$topic['source'] ?? ''] ?? self::SOURCE_DEFAULT_WEIGHT;

        $tier = $topic['publisher_tier'] ?? null;
        $tierBonus = self::TIER_BONUS[$tier] ?? 0;

        $recencyBonus = $this->recencyBonus($topic['pub_date'] ?? null);
        $publisherBonus = $this->publisherCountBonus($topic['publisher_count'] ?? 1);

        $total = $base + $tierBonus + $recencyBonus + $publisherBonus;

        return (int) max(0, min(100, $total));
    }

    private function recencyBonus(?string $pubDate): int
    {
        if ($pubDate === null || $pubDate === '') {
            return 0;
        }

        try {
            $hoursAgo = Carbon::parse($pubDate)->diffInHours(Carbon::now(), false);
        } catch (\Throwable $e) {
            return 0;
        }

        if ($hoursAgo < 0) {
            return 0;
        }

        if ($hoursAgo < 6) {
            return 20;
        }
        if ($hoursAgo < 24) {
            return 15;
        }
        if ($hoursAgo < 72) {
            return 8;
        }
        if ($hoursAgo < 168) {
            return 3;
        }
        return 0;
    }

    private function publisherCountBonus(int $count): int
    {
        if ($count <= 1) {
            return 0;
        }
        if ($count <= 3) {
            return 5;
        }
        if ($count <= 10) {
            return 10;
        }
        return 15;
    }
}
