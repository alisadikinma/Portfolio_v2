<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LinkedInPost;
use App\Models\Setting;

/**
 * Backend-side format-mix governor (May 12, 2026).
 *
 * Plugin /linkedin-brief naturally decides format=text vs format=carousel per
 * draft based on content signals. Production observation: nearly every recent
 * draft outputs format=text, starving Instagram + TikTok cross-post siblings
 * (which only fan out on carousel format per ScanLinkedInForCrossPost).
 *
 * This service enforces a target carousel ratio (default 80% / 20% text) by
 * inspecting the recent draft history and signaling LinkedInGenerationService
 * to re-dispatch the plugin with `format_preference=carousel` when the ratio
 * drifts below target.
 *
 * Bootstrap behavior: when historical drafts < lookback window, no override
 * fires — plugin's natural decision is honored. Ratio stabilizes once the
 * lookback window fills.
 *
 * Terminal statuses (cancelled, failed) excluded from lookback so dropped
 * drafts don't skew the ratio.
 */
class LinkedInFormatMixGovernor
{
    private const DEFAULT_TARGET_RATIO = 0.8;
    private const DEFAULT_LOOKBACK_WINDOW = 10;

    private const EXCLUDED_STATUSES = [
        'cancelled',
        'failed',
    ];

    /**
     * @return bool true when caller (LinkedInGenerationService) should re-dispatch
     *              the plugin with format_preference=carousel
     */
    public function shouldOverrideToCarousel(LinkedInPost $draft, string $pluginEmittedFormat): bool
    {
        if ($pluginEmittedFormat === 'carousel') {
            return false;
        }

        if (! $this->isEnabled()) {
            return false;
        }

        $lookback = $this->lookbackWindow();
        $history = $this->lookbackQuery($draft, $lookback)->get(['format']);

        if ($history->count() < $lookback) {
            // Bootstrap — let plugin decide freely until history fills
            return false;
        }

        $carouselCount = $history->where('format', 'carousel')->count();
        $ratio = $carouselCount / $history->count();

        return $ratio < $this->targetRatio();
    }

    /**
     * Carousel ratio in the most recent active drafts (excludes current draft
     * + terminal statuses). Public for testability / admin UI diagnostics.
     */
    public function computeRatio(LinkedInPost $draft): float
    {
        $lookback = $this->lookbackWindow();
        $history = $this->lookbackQuery($draft, $lookback)->get(['format']);

        if ($history->count() === 0) {
            return 0.0;
        }

        return $history->where('format', 'carousel')->count() / $history->count();
    }

    private function lookbackQuery(LinkedInPost $draft, int $limit)
    {
        return LinkedInPost::query()
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->whereNull('deleted_at')
            ->where('id', '!=', $draft->id)
            ->orderByDesc('created_at')
            ->limit($limit);
    }

    private function isEnabled(): bool
    {
        $value = Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_format_governor_enabled')
            ->value('value');
        return $value !== 'false' && $value !== '0' && $value !== null;
    }

    private function targetRatio(): float
    {
        $value = Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_format_carousel_target_ratio')
            ->value('value');
        if ($value === null) {
            return self::DEFAULT_TARGET_RATIO;
        }
        $float = (float) $value;
        if ($float < 0.0 || $float > 1.0) {
            return self::DEFAULT_TARGET_RATIO;
        }
        return $float;
    }

    private function lookbackWindow(): int
    {
        $value = Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_format_lookback_window')
            ->value('value');
        if ($value === null) {
            return self::DEFAULT_LOOKBACK_WINDOW;
        }
        $int = (int) $value;
        if ($int < 1 || $int > 100) {
            return self::DEFAULT_LOOKBACK_WINDOW;
        }
        return $int;
    }
}
