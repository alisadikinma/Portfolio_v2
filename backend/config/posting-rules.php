<?php

/**
 * Posting time rules — AI research bridge configuration.
 *
 * Mirrors `config/linkedin.php` `generation` section: SSH/local dispatch to
 * `claude` CLI on VPS to run a one-shot WebSearch query and parse the
 * returned JSON of best-time rules. Re-runnable quarterly (auto-scheduled
 * cron in routes/console.php — Phase 4) or operator-triggered via the
 * calendar UI refresh button.
 *
 * Defaults fall back to `LINKEDIN_GEN_*` env vars so a single SSH key /
 * claude path can cover both pipelines without duplicate `.env` lines.
 */
return [
    // 'ssh' (production) or 'local' (dev with claude CLI on host)
    'driver' => env('POSTING_RULES_DRIVER', env('LINKEDIN_GEN_DRIVER', 'ssh')),

    'ssh_host' => env('POSTING_RULES_SSH_HOST', env('LINKEDIN_GEN_SSH_HOST', 'localhost')),
    'ssh_user' => env('POSTING_RULES_SSH_USER', env('LINKEDIN_GEN_SSH_USER', 'claudesn')),
    'ssh_key'  => env('POSTING_RULES_SSH_KEY',  env('LINKEDIN_GEN_SSH_KEY',  '/home/claudesn/.ssh/id_ed25519')),
    'claude_path' => env('POSTING_RULES_CLAUDE_PATH', env('LINKEDIN_GEN_CLAUDE_PATH', 'claude')),

    'model' => env('POSTING_RULES_MODEL', 'sonnet'),

    // ~3 min budget. Research is one round-trip (no per-slide loops).
    'timeout_seconds' => (int) env('POSTING_RULES_TIMEOUT_SECONDS', 180),

    // Empty MCP override — same rationale as LINKEDIN_GEN_EMPTY_MCP_CONFIG
    // (see config/linkedin.php). Pipeline runs don't need MCP servers; loading
    // them risks the obsidian-mcp leak that hit production April 29, 2026.
    'empty_mcp_config' => env('POSTING_RULES_EMPTY_MCP_CONFIG', '/home/claudesn/empty-mcp.json'),

    // Don't refetch rules whose newest row is younger than this. Operator can
    // still --force. 90 days = quarterly cadence aligns with the auto-cron.
    'staleness_days' => (int) env('POSTING_RULES_STALENESS_DAYS', 90),
];
