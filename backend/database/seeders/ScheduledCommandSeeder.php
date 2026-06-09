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
 * Counts (real commands grow over time; placeholders fixed at 4):
 *   - 5 content_engine (+ content:flag-stale-posts, see June 9 ship)
 *   - 8 linkedin (incl. social-cross-post:scan, promoted from a static
 *                 routes/console.php fallback — June 9, 2026)
 *   - 1 newsletter
 *   - 1+ system (posting-rules:research, geminigen:circuit-probe)
 *   + 4 placeholder (instagram x2, facebook x1, tiktok x1), is_placeholder=true
 *
 * The exact total drifts as commands ship — the /admin/scheduler tab renders
 * whatever is seeded. Treat the per-category lists below as authoritative,
 * not the summary count.
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
                'description' => 'Jalanin artikel yang punya jadwal manual (kolom scheduled_at). Tiap menit cek, kalau waktunya udah lewat → start research. Untuk timing release spesifik (mis. follow-up news cycle).',
                'category' => 'content_engine',
                'cron_expression' => '* * * * *',
                'without_overlapping_minutes' => null,
                'arguments' => null,
                'sort_order' => 10,
            ],
            [
                'signature' => 'blog:process-images',
                'display_name' => 'Content Engine — Poll GeminiGen Images',
                'description' => 'Fallback poller untuk GeminiGen. Kalau webhook gambar drop / lambat, command ini cek manual dan download yang sudah ready. Wajib ENABLE saat clear backlog.',
                'category' => 'content_engine',
                'cron_expression' => '* * * * *',
                'without_overlapping_minutes' => null,
                'arguments' => null,
                'sort_order' => 20,
            ],
            [
                'signature' => 'content:process-pending-translations',
                'display_name' => 'Content Engine — Retry Pending Translations',
                'description' => 'Retry translation EN untuk post yang gagal ditranslate (max 3x). Tiap 5 menit.',
                'category' => 'content_engine',
                'cron_expression' => '*/5 * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 30,
            ],
            [
                'signature' => 'content:auto-pipeline',
                'display_name' => 'Content Engine — Auto Pipeline (Bulk Auto-Publish)',
                'description' => 'Antrian otomatis: pick 1 idea dengan Auto=ON & virality tertinggi → jalanin full pipeline (research → write → score → images → publish) sampai selesai, lanjut ke berikutnya. Untuk clear backlog set ke "Every minute".',
                'category' => 'content_engine',
                'cron_expression' => '0 5,6,12,15,17,18,19,20 * * *',
                'without_overlapping_minutes' => 10,
                'arguments' => null,
                'sort_order' => 40,
            ],
            [
                'signature' => 'content:pull-trending-daily',
                'display_name' => 'Content Engine — Pull Trending Daily',
                'description' => 'Tiap pagi jam 5: ambil trending dari Google News + Trends, AI-score, import yang virality ≥ 70 sebagai draft auto-mode. Sumber utama backlog idea.',
                'category' => 'content_engine',
                'cron_expression' => '0 5 * * *',
                'without_overlapping_minutes' => null,
                'arguments' => null,
                'sort_order' => 50,
            ],
            [
                'signature' => 'content:flag-stale-posts',
                'display_name' => 'Content Engine — Flag Stale Posts (Freshness)',
                'description' => 'Tiap Senin jam 6 pagi: tandai blog post yang anchor freshness-nya (published_at / content_reviewed_at) > 90 hari, kirim SATU digest Telegram. Operator yang putuskan refresh atau mark reviewed — tidak ada auto-regenerate.',
                'category' => 'content_engine',
                'cron_expression' => '0 6 * * 1',
                'without_overlapping_minutes' => 30,
                'arguments' => ['--days=90'],
                'sort_order' => 60,
            ],

            // ─────────────── LinkedIn (7) ───────────────
            [
                // P5 (May 12, 2026): atomic orchestrator replaces
                // linkedin:process-scheduled. Same * * * * * tick, but knows
                // about carousel siblings (IG/TT/TH) and dispatches all to
                // fire same minute when ready. Legacy linkedin:process-scheduled
                // remains in ProcessScheduledLinkedInPosts.php as a delegating
                // stub through P7, then deleted.
                'signature' => 'social:publish-slot',
                'display_name' => 'Social — Atomic Publish at Slot (LinkedIn + IG/TT/TH)',
                'description' => 'Tiap menit cek antrian LinkedIn + sibling IG/TikTok/Threads. Kalau ada draft siap publish di slot waktu sekarang → fire ke API platform secara atomic (semua platform jalan bareng).',
                'category' => 'linkedin',
                'cron_expression' => '* * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 10,
            ],
            [
                'signature' => 'linkedin:scan-blog',
                'display_name' => 'LinkedIn — Scan Blog for Conversion',
                'description' => 'Tiap pagi jam 3: scan blog post baru 24 jam terakhir. Yang virality ≥ 60 dan belum punya draft LinkedIn → buat draft pending generation.',
                'category' => 'linkedin',
                'cron_expression' => '0 3 * * *',
                'without_overlapping_minutes' => 30,
                'arguments' => ['--hours=24'],
                'sort_order' => 20,
            ],
            [
                'signature' => 'linkedin:reap-stuck',
                'display_name' => 'LinkedIn — Reap Stuck Generation',
                'description' => 'Tiap 5 menit: cek draft yang stuck di pending_generation > 30 menit → re-dispatch atau mark failed.',
                'category' => 'linkedin',
                'cron_expression' => '*/5 * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 30,
            ],
            [
                'signature' => 'linkedin:retry-failed',
                'display_name' => 'LinkedIn — Auto-Retry Failed Drafts',
                'description' => 'Tiap 10 menit: retry draft failed yang error class-nya transient (network glitch, timeout). Skip permanent error.',
                'category' => 'linkedin',
                'cron_expression' => '*/10 * * * *',
                'without_overlapping_minutes' => 15,
                'arguments' => null,
                'sort_order' => 40,
            ],
            [
                'signature' => 'linkedin:reap-stuck-carousel-images',
                'display_name' => 'LinkedIn — Reap Stuck Carousel Images',
                'description' => 'Tiap 5 menit: cek slide carousel yang stuck rendering > 15 menit → re-dispatch ke GeminiGen. Catches webhook drops.',
                'category' => 'linkedin',
                'cron_expression' => '*/5 * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 50,
            ],
            [
                'signature' => 'linkedin:purge-low-virality',
                'display_name' => 'LinkedIn — Purge Low-Virality Drafts',
                'description' => 'Tiap pagi jam 4: soft-delete draft yang virality source idea-nya turun < 50. Skip yang udah published / cancelled / awaiting_publish.',
                'category' => 'linkedin',
                'cron_expression' => '0 4 * * *',
                'without_overlapping_minutes' => 15,
                'arguments' => null,
                'sort_order' => 60,
            ],
            [
                'signature' => 'linkedin:auto-schedule',
                'display_name' => 'LinkedIn — Auto-Schedule Manual Review',
                'description' => 'Tiap pagi jam 4:30: promote draft manual_review → awaiting_publish di slot waktu kosong (urut virality DESC). Gated by master kill switch (default OFF).',
                'category' => 'linkedin',
                'cron_expression' => '30 4 * * *',
                'without_overlapping_minutes' => 15,
                'arguments' => null,
                'sort_order' => 70,
            ],
            [
                // Default-carousel fan-out reaper (June 9, 2026). Promoted from
                // a static routes/console.php fallback to a DB-driven row so it
                // shows up in /admin/settings?tab=scheduler and is operator-
                // tunable. Every 2 min: fan out carousel LinkedIn drafts whose
                // slides are 'done' to IG/TikTok/Threads/FB (event-driven
                // dispatch from the carousel webhook is the fast path; this is
                // the safety reaper).
                'signature' => 'social-cross-post:scan',
                'display_name' => 'Social — Cross-Post Fan-Out (IG/TikTok/Threads/FB)',
                'description' => 'Tiap 2 menit: fan out draft LinkedIn carousel yang slide-nya sudah selesai render ke Instagram + TikTok + Threads + Facebook. Idempotent — skip yang sudah punya sibling.',
                'category' => 'linkedin',
                'cron_expression' => '*/2 * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 80,
            ],

            // ─────────────── Newsletter (1) ───────────────
            [
                'signature' => 'newsletter:send-weekly',
                'display_name' => 'Newsletter — Weekly Friday Digest',
                'description' => 'Jumat jam 9 pagi: kirim weekly digest ke semua subscriber. Skip kalau seminggu nggak ada blog post baru (no spam).',
                'category' => 'newsletter',
                'cron_expression' => '0 9 * * 5',
                'without_overlapping_minutes' => 60,
                'arguments' => null,
                'sort_order' => 10,
            ],

            // ─────────────── System (2) ───────────────
            [
                'signature' => 'posting-rules:research',
                'display_name' => 'Posting Rules — Quarterly Research (LinkedIn)',
                'description' => 'Tiap 3 bulan (Jan/Apr/Jul/Okt jam 3 pagi): AI riset jam-jam terbaik publish LinkedIn untuk audience b2b_tech. Output ke posting_time_rules table — dipakai LinkedIn auto-scheduler.',
                'category' => 'system',
                'cron_expression' => '0 3 1 */3 *',
                'without_overlapping_minutes' => 15,
                'arguments' => ['--platform=linkedin'],
                'sort_order' => 10,
            ],
            [
                // Phase H (May 15, 2026) — GeminiGen circuit breaker canary
                // probe. No-ops unless breaker is OPEN past next_probe_at.
                'signature' => 'geminigen:circuit-probe',
                'display_name' => 'GeminiGen — Circuit Breaker Canary Probe',
                'description' => 'Tiap 5 menit cek status GeminiGen.ai (hanya saat circuit OPEN). Kalau status page healthy → coba half_open. Hemat kuota saat outage.',
                'category' => 'system',
                'cron_expression' => '*/5 * * * *',
                'without_overlapping_minutes' => 5,
                'arguments' => null,
                'sort_order' => 20,
            ],
        ];

        foreach ($rows as $row) {
            $cmd = ScheduledCommand::firstOrCreate(
                ['signature' => $row['signature']],
                array_merge($row, [
                    'timezone' => 'Asia/Jakarta',
                    'enabled' => true,
                    'is_placeholder' => false,
                    'last_status' => 'never',
                ])
            );

            // Sync description on every re-seed so operator-edited cron/enabled
            // stays intact but documentation stays in lockstep with code.
            // Operator never edits description from the UI — it's docs, not config.
            if ($cmd->description !== ($row['description'] ?? null)) {
                $cmd->update([
                    'description' => $row['description'] ?? null,
                    'display_name' => $row['display_name'],
                ]);
            }
        }

        // P5 (May 12, 2026) — retire the legacy linkedin:process-scheduled
        // scheduled row. The Artisan command itself remains as a delegating
        // alias (calls social:publish-slot) through P7 for any external
        // caller that hardcoded the old signature. But the cron-driven row
        // must be removed so we don't double-publish every minute.
        ScheduledCommand::where('signature', 'linkedin:process-scheduled')->delete();

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
