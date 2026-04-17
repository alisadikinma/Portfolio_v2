<?php

return [
    'default_image_model' => env('DEFAULT_IMAGE_MODEL', 'nano-banana-pro'),

    'cover_branding' => [
        'enabled' => env('COVER_BRANDING_ENABLED', true),
        'model' => env('COVER_BRANDING_MODEL', 'nano-banana-pro'),
        'title_max_len' => (int) env('COVER_BRANDING_TITLE_MAX_LEN', 70),
    ],
];
