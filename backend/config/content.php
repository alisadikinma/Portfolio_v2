<?php

return [
    'default_image_model' => env('DEFAULT_IMAGE_MODEL', 'nano-banana-pro'),

    // AutoPipelineOrchestrator operating window — only advances auto_mode ideas
    // between window_start (inclusive) and window_end (exclusive) in the given
    // timezone. Prevents overnight SSH dispatches that would wake up at 3 AM.
    'auto_timezone' => env('CONTENT_AUTO_TIMEZONE', 'Asia/Jakarta'),
    'auto_window_start' => (int) env('CONTENT_AUTO_WINDOW_START', 6),
    'auto_window_end' => (int) env('CONTENT_AUTO_WINDOW_END', 22),

    // Minimum gap (minutes) between starting successive draft articles. The
    // orchestrator stamps a cache key every time startNextDraft() succeeds
    // and skips picking a new draft until this many minutes have elapsed.
    // Publish / image dispatch / retry are NOT affected — only the cadence
    // of brand-new article generation. Default 30 min spreads publishing
    // throughout the day and avoids flooding the blog feed.
    'auto_start_interval_minutes' => (int) env('CONTENT_AUTO_START_INTERVAL_MINUTES', 30),

    'cover_branding' => [
        'enabled' => env('COVER_BRANDING_ENABLED', true),
        'model' => env('COVER_BRANDING_MODEL', 'nano-banana-pro'),
        'title_max_len' => (int) env('COVER_BRANDING_TITLE_MAX_LEN', 70),
    ],

    // Trending Topics — display_score boost weights. The UI surfaces ONE number
    // per card (display_score) instead of juggling heat + tier + virality
    // badges. These weights get added to the AI composite_score, clamped 0-100.
    // Tune if the feed becomes over-hot (everything green 90+) or over-flat
    // (hot items not surfacing). See docs/plans/2026-04-19-trending-topics-ui-refactor.md.
    'trending' => [
        'heat_boost' => [
            'hot' => (int) env('TRENDING_HEAT_BOOST_HOT', 15),
            'trending' => (int) env('TRENDING_HEAT_BOOST_TRENDING', 8),
            'standard' => 0,
        ],
        'tier_boost' => [
            1 => (int) env('TRENDING_TIER_BOOST_1', 5),
            2 => (int) env('TRENDING_TIER_BOOST_2', 2),
            3 => 0,
        ],
        // Max topics to run through AI scoring per Pull Trending call.
        // Clamped to a SINGLE Sonnet batch (1..MAX_BATCH_SIZE=20) in
        // getScoredTopics() so the interactive modal stays under Cloudflare's
        // ~100s origin wall. Topics are momentum-sorted first, so this is the
        // top-N highest-momentum candidates. Raise toward 20 if the modal feels
        // thin; values >20 are capped (one batch only).
        'max_scored' => (int) env('TRENDING_MAX_SCORED', 12),

        // Hard gate for the daily auto-import (PullTrendingDaily) AND the
        // manual "Pull Trending" modal import. Topics whose AI-scored
        // virality_score falls below this threshold are skipped — they never
        // reach the content_ideas table. Default 80 is the editorial bar for
        // "this is worth writing about". Lower this to widen the funnel during
        // slow news cycles, raise it when the queue is overflowing.
        'virality_threshold' => (int) env('TRENDING_VIRALITY_THRESHOLD', 80),

        // Freshness gate applied at pull time (manual "Pull Trending" modal
        // AND the daily PullTrendingDaily cron). News items carrying a
        // pub_date older than this many days are dropped before scoring —
        // stale articles never reach the modal or content_ideas. Set to 1
        // for ~same-day-only ("hari H"). Items with no pub_date (Google
        // Trends / TikTok / YouTube — inherently live trend feeds) are kept.
        // 0 disables the gate.
        'max_age_days' => (int) env('TRENDING_MAX_AGE_DAYS', 3),

        // Maximum topics the daily auto-import (PullTrendingDaily) will
        // insert per run, selected by virality_score DESC after the threshold
        // gate. This prevents queue flooding on high-signal days while
        // guaranteeing the content pipeline always gets the BEST topics.
        // Dedup still applies inside the cap — a duplicate slot is simply
        // dropped (no auto-backfill from lower-ranked topics by design).
        // Tune up during content sprints, down to 1 for single-topic cadence.
        'daily_pick_limit' => (int) env('TRENDING_DAILY_PICK_LIMIT', 5),
    ],
];
