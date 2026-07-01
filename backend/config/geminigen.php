<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GeminiGen / snapgen base URL
    |--------------------------------------------------------------------------
    |
    | The vendor rebranded api.geminigen.ai -> api.snapgen.ai. This is the single
    | source of the base URL for every backend GeminiGen call (submit + history
    | poll). The three services and the poll crons read it from here instead of
    | hardcoding, so a future domain flip is one env change.
    |
    */
    'base_url' => env('GEMINIGEN_BASE_URL', 'https://api.snapgen.ai/uapi/v1'),

    /*
    |--------------------------------------------------------------------------
    | indusia client bridge (SSOT transport)
    |--------------------------------------------------------------------------
    |
    | The backend shells the indusiagen-api-client CLI (geminigen_image.py /
    | geminigen_video.py) for submit + poll-check so the Python client owns the
    | wire protocol. `driver`: 'local' (direct Process::run of the venv python;
    | correct for queue-dispatched paths running as claudesn) or 'ssh' (www-data
    | HTTP-context -> claudesn@localhost, same two-context key rule as
    | ARTICLE_GEN_* / LINKEDIN_GEN_*). The API key is NOT passed here — the client
    | resolves it from its own .env on the VPS (never on argv, visible in `ps`).
    |
    */
    'client_driver' => env('GEMINIGEN_CLIENT_DRIVER', 'local'),
    'client_path' => env('GEMINIGEN_CLIENT_PATH', '/home/claudesn/indusiagen-api-client/.venv/bin/python'),
    'client_repo' => env('GEMINIGEN_CLIENT_REPO', '/home/claudesn/indusiagen-api-client'),
    'client_ssh_host' => env('GEMINIGEN_CLIENT_SSH_HOST', 'localhost'),
    'client_ssh_user' => env('GEMINIGEN_CLIENT_SSH_USER', 'claudesn'),
    'client_ssh_key' => env('GEMINIGEN_CLIENT_SSH_KEY', '/home/claudesn/.ssh/id_ed25519'),
    'client_timeout' => (int) env('GEMINIGEN_CLIENT_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Rollout flags (default OFF — old PHP HTTP path is the fallback per surface)
    |--------------------------------------------------------------------------
    */
    'use_indusia_images' => (bool) env('GEMINIGEN_USE_INDUSIA_IMAGES', false),
    'use_indusia_video' => (bool) env('GEMINIGEN_USE_INDUSIA_VIDEO', false),

];
