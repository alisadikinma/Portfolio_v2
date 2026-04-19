<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
    /** Maximum topics per Sonnet batch to avoid prompt truncation. */
    public const MAX_BATCH_SIZE = 20;

    /** Composite score cache TTL (1 hour — matches trend refresh cadence). */
    private const CACHE_TTL_SECONDS = 3600;

    /** Cache namespace version — bump when scoring formula changes. */
    private const CACHE_VERSION = 'v1';

    /** Composite weight for mechanical momentum signal (0..1). */
    private const MOMENTUM_WEIGHT = 0.4;

    /** Composite weight for AI virality signal (0..1). */
    private const VIRALITY_WEIGHT = 0.6;

    private const VIRALITY_TRIGGERS = [
        'social_currency',
        'high_arousal',
        'practical_utility',
        'identity_signaling',
        'cognitive_gap',
    ];

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

    /**
     * Batch-score topics for virality using a single Sonnet call.
     * Returns the input array augmented with virality_score + triggers per row.
     *
     * @param array $topics each element must have at least 'title'. Optional: source, description.
     * @return array same order as input, each enriched with virality_score + triggers.
     * @throws \InvalidArgumentException if batch exceeds MAX_BATCH_SIZE.
     */
    public function scoreViralityBatch(array $topics): array
    {
        return $this->scoreViralityBatchWithStatus($topics)['topics'];
    }

    /**
     * Internal variant that also reports whether the AI leg succeeded.
     * scoreBatch() uses this to decide whether a result deserves caching.
     *
     * @return array{topics: array, ai_succeeded: bool}
     */
    private function scoreViralityBatchWithStatus(array $topics): array
    {
        if (count($topics) === 0) {
            return ['topics' => [], 'ai_succeeded' => true];
        }
        if (count($topics) > self::MAX_BATCH_SIZE) {
            throw new \InvalidArgumentException(
                'Topic batch exceeds ' . self::MAX_BATCH_SIZE . ' topic cap (got ' . count($topics) . ')'
            );
        }

        $prompt = $this->buildViralityPrompt($topics);
        $articleGen = app(ArticleGenerationService::class);

        try {
            $result = $articleGen->runSonnetSync($prompt, 'topic-scoring', 'sonnet');
        } catch (\Throwable $e) {
            Log::warning('[TopicScoring] Sonnet call threw', ['error' => $e->getMessage()]);
            return ['topics' => $this->zeroScoreAll($topics), 'ai_succeeded' => false];
        }

        if (empty($result['success']) || empty($result['output'])) {
            Log::warning('[TopicScoring] Sonnet returned failure', [
                'error' => $result['error'] ?? null,
            ]);
            return ['topics' => $this->zeroScoreAll($topics), 'ai_succeeded' => false];
        }

        $parsed = $this->parseViralityResponse($result['output']);
        if ($parsed === null) {
            Log::warning('[TopicScoring] Failed to parse Sonnet output as JSON', [
                'output_head' => substr((string) $result['output'], 0, 200),
            ]);
            return ['topics' => $this->zeroScoreAll($topics), 'ai_succeeded' => false];
        }

        return [
            'topics' => $this->mergeViralityResults($topics, $parsed),
            'ai_succeeded' => true,
        ];
    }

    private function buildViralityPrompt(array $topics): string
    {
        $lines = [];
        foreach (array_values($topics) as $i => $t) {
            $idx = $i + 1;
            $title = $t['title'] ?? '';
            $source = $t['source'] ?? 'unknown';
            $desc = trim((string) ($t['description'] ?? ''));
            $descSnippet = $desc !== '' ? ' | ' . mb_substr($desc, 0, 240) : '';
            $lines[] = "{$idx}. [{$source}] {$title}{$descSnippet}";
        }
        $listed = implode("\n", $lines);
        $count = count($topics);

        return <<<PROMPT
You are scoring {$count} trending topics for viral potential. For each topic, evaluate:
- virality_score: integer 0-100 (0 = inert, 100 = extreme viral potential)
- triggers: 5 booleans from Jonah Berger's STEPPS framework:
  * social_currency: sharing it makes the sharer look smart/in-the-know
  * high_arousal: provokes strong emotion (awe, anger, excitement)
  * practical_utility: useful, actionable info people save/send
  * identity_signaling: aligns with tribe/profession identity
  * cognitive_gap: creates curiosity gap that demands resolution

Topics:
{$listed}

Return ONLY a JSON array, one object per input topic in the SAME ORDER, with this exact shape:
[
  {"title":"...","virality_score":80,"triggers":{"social_currency":true,"high_arousal":true,"practical_utility":false,"identity_signaling":true,"cognitive_gap":false}}
]

No prose, no markdown, no code fences. Pure JSON array only.
PROMPT;
    }

    private function parseViralityResponse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Strip common wrappers like ```json ... ```
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }

        // Find the first JSON array/object
        $start = strpos($raw, '[');
        if ($start === false) {
            return null;
        }
        $end = strrpos($raw, ']');
        if ($end === false || $end <= $start) {
            return null;
        }
        $slice = substr($raw, $start, ($end - $start) + 1);

        $decoded = json_decode($slice, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function mergeViralityResults(array $topics, array $aiResults): array
    {
        $out = [];
        foreach (array_values($topics) as $i => $topic) {
            $ai = $aiResults[$i] ?? null;

            $score = 0;
            $triggers = $this->emptyTriggers();

            if (is_array($ai)) {
                $rawScore = $ai['virality_score'] ?? 0;
                $score = (int) max(0, min(100, (int) $rawScore));

                if (isset($ai['triggers']) && is_array($ai['triggers'])) {
                    foreach (self::VIRALITY_TRIGGERS as $key) {
                        $triggers[$key] = (bool) ($ai['triggers'][$key] ?? false);
                    }
                }
            }

            $topic['virality_score'] = $score;
            $topic['triggers'] = $triggers;
            $out[] = $topic;
        }
        return $out;
    }

    private function zeroScoreAll(array $topics): array
    {
        $out = [];
        foreach (array_values($topics) as $topic) {
            $topic['virality_score'] = 0;
            $topic['triggers'] = $this->emptyTriggers();
            $out[] = $topic;
        }
        return $out;
    }

    private function emptyTriggers(): array
    {
        $triggers = [];
        foreach (self::VIRALITY_TRIGGERS as $key) {
            $triggers[$key] = false;
        }
        return $triggers;
    }

    /**
     * Top-level scoring entry point. Combines mechanical momentum + AI virality
     * into a composite 0-100 score with 1-hour cache keyed by title set.
     *
     * Each returned topic carries the original fields plus: momentum_score,
     * virality_score, triggers, composite_score.
     */
    public function scoreBatch(array $topics): array
    {
        if (count($topics) === 0) {
            return [];
        }

        $key = $this->cacheKey($topics);

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        // Mechanical momentum first — cheap, no AI.
        $withMomentum = [];
        foreach (array_values($topics) as $topic) {
            $topic['momentum_score'] = $this->computeMomentum($topic);
            $withMomentum[] = $topic;
        }

        // Virality AI scoring (single batch call) — track success so we can
        // skip the cache write on AI failure and avoid poisoning the key
        // with zero-scores for the next hour.
        $result = $this->scoreViralityBatchWithStatus($withMomentum);

        // Composite = weighted average, int-clamped.
        $out = [];
        foreach ($result['topics'] as $topic) {
            $momentum = (int) ($topic['momentum_score'] ?? 0);
            $virality = (int) ($topic['virality_score'] ?? 0);
            $composite = (int) round(
                $momentum * self::MOMENTUM_WEIGHT + $virality * self::VIRALITY_WEIGHT
            );
            $topic['composite_score'] = max(0, min(100, $composite));
            $out[] = $topic;
        }

        if ($result['ai_succeeded']) {
            Cache::put($key, $out, self::CACHE_TTL_SECONDS);
        }

        return $out;
    }

    private function cacheKey(array $topics): string
    {
        $titles = array_map(
            fn ($t) => (string) ($t['title'] ?? ''),
            array_values($topics)
        );
        return 'topic_scores_' . self::CACHE_VERSION . '_' . sha1(implode('|', $titles));
    }
}
