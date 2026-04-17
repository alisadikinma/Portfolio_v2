<?php

namespace App\Services;

use App\Models\ContentIdea;
use Illuminate\Support\Str;

class TopicDedupService
{
    public const SIMILARITY_THRESHOLD = 85.0;
    public const LOOKBACK_DAYS = 30;

    /**
     * Detects duplicates of $title against ContentIdea titles created within
     * the last LOOKBACK_DAYS. Returns the matching idea, or null if unique.
     *
     * Matching rules (first match wins, order of precedence):
     *   1. Slug equality (case-insensitive, whitespace-normalized)
     *   2. similar_text percentage >= SIMILARITY_THRESHOLD
     */
    public function isDuplicate(string $title): ?ContentIdea
    {
        $incoming = trim($title);
        if ($incoming === '') {
            return null;
        }

        $incomingSlug = Str::slug($incoming);

        $candidates = ContentIdea::where('created_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->get(['id', 'title', 'slug', 'pillar', 'source', 'status', 'created_at']);

        foreach ($candidates as $existing) {
            if (Str::slug((string) $existing->title) === $incomingSlug) {
                return $existing;
            }
        }

        foreach ($candidates as $existing) {
            similar_text(
                mb_strtolower($incoming),
                mb_strtolower((string) $existing->title),
                $percent
            );
            if ($percent >= self::SIMILARITY_THRESHOLD) {
                return $existing;
            }
        }

        return null;
    }
}
