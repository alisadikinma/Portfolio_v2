<?php

/*
|--------------------------------------------------------------------------
| GeminiGen Relay Webhook
|--------------------------------------------------------------------------
|
| Configuration for the stateless webhook relay used by the
| `geminigen-api-client` Claude Code plugin. The plugin sets its
| webhook_url to /api/automation/geminigen/webhook with a base64url-
| encoded caller callback URL and a Sanctum bearer token in query
| string. Portfolio_v2 verifies inbound RSA signature against the
| public key at the path below, decodes the callback URL, and
| forwards the payload as-is to the caller.
|
| See docs/plans/2026-05-14-geminigen-relay-webhook.md
|
*/

return [

    'public_key_path' => env('GEMINIGEN_RELAY_PUBLIC_KEY_PATH'),

    'forward_timeout' => (int) env('GEMINIGEN_RELAY_FORWARD_TIMEOUT', 15),

    'forward_retries' => (int) env('GEMINIGEN_RELAY_FORWARD_RETRIES', 2),

    'forward_retry_delay_ms' => (int) env('GEMINIGEN_RELAY_FORWARD_RETRY_DELAY_MS', 1000),

];
