<?php

return [
    'default_image_model' => env('DEFAULT_IMAGE_MODEL', 'nano-banana-pro'),

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
        // Scorer chunks in batches of TopicScoringService::MAX_BATCH_SIZE (20)
        // so 60 = 3 Sonnet calls. Raise when the admin modal feels too thin;
        // each extra chunk adds ~3-5s latency + 1 Sonnet invocation.
        'max_scored' => (int) env('TRENDING_MAX_SCORED', 60),
    ],
];
