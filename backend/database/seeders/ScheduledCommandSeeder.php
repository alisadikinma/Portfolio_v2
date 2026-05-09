<?php

namespace Database\Seeders;

use App\Models\ScheduledCommand;
use Illuminate\Database\Seeder;

/**
 * Phase B — admin scheduler tab seed data.
 *
 * Seeds the authoritative inventory of artisan commands the operator can
 * toggle from /admin/scheduler. Mirrors the live entries currently in
 * routes/console.php plus 4 placeholder slots reserved for the IG/FB/TikTok
 * roadmap.
 *
 * Counts:
 *   - 5 content_engine
 *   - 7 linkedin
 *   - 1 newsletter
 *   - 1 system (posting-rules:research)
 *   = 14 real, all enabled, is_placeholder=false
 *   + 4 placeholder (instagram x2, facebook x1, tiktok x1)
 *   = 18 rows total
 *
 * (The plan draft mentioned "19 rows" but the actual real-command count
 * tallies to 14; the 4 placeholders bring the seed total to 18. The
 * /admin/scheduler tab still has space to add more roadmap rows later.)
 *
 * Idempotent via firstOrCreate keyed on `signature` — safe to re-run on
 * production (deploy.sh step 4 runs idempotent seeders).
 */
class ScheduledCommandSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ─────────────── Content Engine (5) ───────────────
            [
                'signature' => 'content:process-scheduled',
                'display_name' => 'Content Engine — Process Scheduled Ideas',
                'category' => 'content_engine',
                'cron_expression' => '* * * * *',
                'without_overlapping_minutes' => null,
                'arguments' => null,
                'sort_order' => 10,
            ],
            [
                'signature' => 'blog:process-images',
                'display_name' => 'Content Engine — Poll GeminiGen Images',
                'category' => 'content_engine',
                'cron_expression' => '* * * * *',
                'without_overlapping_minutes' => null,
                'arguments' => null,
                'sort_order' => 20,
            ],
            [
                'signature' => 'content:process-pending-translations',
                'display_name' => 'Content Engine — Retry Pending Translations',
                'category' => 'content_engine',
                'cron_expression' => '*/5 * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 30,
            ],
            [
                'signature' => 'content:auto-pipeline',
                'display_name' => 'Content Engine — Auto Pipeline (8x daily)',
                'category' => 'content_engine',
                'cron_expression' => '0 5,6,12,15,17,18,19,20 * * *',
                'without_overlapping_minutes' => 10,
                'arguments' => null,
                'sort_order' => 40,
            ],
            [
                'signature' => 'content:pull-trending-daily',
                'display_name' => 'Content Engine — Pull Trending Daily',
                'category' => 'content_engine',
                'cron_expression' => '0 5 * * *',
                'without_overlapping_minutes' => null,
                'arguments' => null,
                'sort_order' => 50,
            ],

            // ─────────────── LinkedIn (7) ───────────────
            [
                'signature' => 'linkedin:process-scheduled',
                'display_name' => 'LinkedIn — Process Scheduled Posts',
                'category' => 'linkedin',
                'cron_expression' => '* * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 10,
            ],
            [
                'signature' => 'linkedin:scan-blog',
                'display_name' => 'LinkedIn — Scan Blog for Conversion',
                'category' => 'linkedin',
                'cron_expression' => '0 3 * * *',
                'without_overlapping_minutes' => 30,
                'arguments' => ['--hours=24'],
                'sort_order' => 20,
            ],
            [
                'signature' => 'linkedin:reap-stuck',
                'display_name' => 'LinkedIn — Reap Stuck Generation',
                'category' => 'linkedin',
                'cron_expression' => '*/5 * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 30,
            ],
            [
                'signature' => 'linkedin:retry-failed',
                'display_name' => 'LinkedIn — Auto-Retry Failed Drafts',
                'category' => 'linkedin',
                'cron_expression' => '*/10 * * * *',
                'without_overlapping_minutes' => 15,
                'arguments' => null,
                'sort_order' => 40,
            ],
            [
                'signature' => 'linkedin:reap-stuck-carousel-images',
                'display_name' => 'LinkedIn — Reap Stuck Carousel Images',
                'category' => 'linkedin',
                'cron_expression' => '*/5 * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 50,
            ],
            [
                'signature' => 'linkedin:purge-low-virality',
                'display_name' => 'LinkedIn — Purge Low-Virality Drafts',
                'category' => 'linkedin',
                'cron_expression' => '0 4 * * *',
                'without_overlapping_minutes' => 15,
                'arguments' => null,
                'sort_order' => 60,
            ],
            [
                'signature' => 'linkedin:auto-schedule',
                'display_name' => 'LinkedIn — Auto-Schedule Manual Review',
                'category' => 'linkedin',
                'cron_expression' => '30 4 * * *',
                'without_overlapping_minutes' => 15,
                'arguments' => null,
                'sort_order' => 70,
            ],

            // ─────────────── Newsletter (1) ───────────────
            [
                'signature' => 'newsletter:send-weekly',
                'display_name' => 'Newsletter — Weekly Friday Digest',
                'category' => 'newsletter',
                'cron_expression' => '0 9 * * 5',
                'without_overlapping_minutes' => 60,
                'arguments' => null,
                'sort_order' => 10,
            ],

            // ─────────────── System (1) ───────────────
            [
                'signature' => 'posting-rules:research',
                'display_name' => 'Posting Rules — Quarterly Research (LinkedIn)',
                'category' => 'system',
                'cron_expression' => '0 3 1 */3 *',
                'without_overlapping_minutes' => 15,
                'arguments' => ['--platform=linkedin'],
                'sort_order' => 10,
            ],
        ];

        foreach ($rows as $row) {
            ScheduledCommand::firstOrCreate(
                ['signature' => $row['signature']],
                array_merge($row, [
                    'timezone' => 'Asia/Jakarta',
                    'enabled' => true,
                    'is_placeholder' => false,
                    'last_status' => 'never',
                ])
            );
        }

        // ─────────────── Placeholders (4 — disabled, reserved for roadmap) ───────────────
        $placeholders = [
            [
                'signature' => 'placeholder:instagram-scan',
                'display_name' => 'Instagram — Scan Blog (Coming soon)',
                'category' => 'instagram',
                'cron_expression' => '0 3 * * *',
                'sort_order' => 10,
            ],
            [
                'signature' => 'placeholder:instagram-publish',
                'display_name' => 'Instagram — Auto Publish (Coming soon)',
                'category' => 'instagram',
                'cron_expression' => '* * * * *',
                'sort_order' => 20,
            ],
            [
                'signature' => 'placeholder:facebook-publish',
                'display_name' => 'Facebook — Auto Publish (Coming soon)',
                'category' => 'facebook',
                'cron_expression' => '* * * * *',
                'sort_order' => 10,
            ],
            [
                'signature' => 'placeholder:tiktok-publish',
                'display_name' => 'TikTok — Auto Publish (Coming soon)',
                'category' => 'tiktok',
                'cron_expression' => '* * * * *',
                'sort_order' => 10,
            ],
        ];

        foreach ($placeholders as $row) {
            ScheduledCommand::firstOrCreate(
                ['signature' => $row['signature']],
                array_merge($row, [
                    'timezone' => 'Asia/Jakarta',
                    'enabled' => false,
                    'is_placeholder' => true,
                    'last_status' => 'never',
                    'arguments' => null,
                    'without_overlapping_minutes' => null,
                ])
            );
        }
    }
}
