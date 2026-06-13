<?php

namespace App\Console\Commands;

use App\Jobs\PublishViaPubler;
use App\Models\PostizPublishJob;
use App\Models\Setting;
use App\Services\PublerPayloadBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * postiz:reap-unclaimed — claim-aware deadline watchdog (every minute).
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase F).
 *
 * Anti-double-publish is the WHOLE point here, so the eligibility split is
 * deliberately conservative (hardened after code review):
 *
 *   NEVER-CLAIMED (PC was offline at the slot — claimed_at IS NULL, status ready):
 *     Postiz definitively never saw the job → SAFE to auto-Publer (Publer-capable
 *     platforms) or wait+alert (IG-video/Medium, which only Postiz can publish).
 *
 *   POLLER-TOUCHED (status=claimed lease-expired, OR status=failed): the poller
 *     claimed the job and went silent, OR reported a failure that may STILL have
 *     reached Postiz (a network timeout AFTER Postiz committed looks identical to
 *     a clean reject). This window is genuinely ambiguous, so we NEVER auto-Publer
 *     it — that's exactly the crash-after-accept double-publish race. Instead we
 *     park it as `needs_review` (not claimable → the poller won't re-take and
 *     re-POST either) and alert the operator to confirm whether it went live.
 *
 *   ACCEPTED+ (postiz_post_id set): Postiz/Temporal owns it — fully excluded, even
 *     on a later ERROR (routes to manual handling via the result callback).
 *
 * A late `accepted`/`published` callback from a slow-but-alive poller still
 * recovers a `needs_review` job (result() only treats published/failed as
 * terminal), so a genuinely slow publish self-heals; only a dead poller leaves
 * the job for the operator.
 */
class ReapUnclaimedPostizJobs extends Command
{
    protected $signature = 'postiz:reap-unclaimed {--dry-run : Log decisions without dispatching}';

    protected $description = 'Fire Publer fallback for Postiz jobs the local node never published (claim-aware, anti-double-publish)';

    /** Platforms Publer can publish (the fallback set). Excludes medium + IG-video. */
    private const PUBLER_CAPABLE = ['instagram', 'tiktok', 'threads', 'facebook'];

    public function handle(PublerPayloadBuilder $builder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deadline = (int) (Setting::where('group', 'postiz')->where('key', 'postiz_fallback_deadline_minutes')->value('value') ?? 6);
        $alertMinutes = (int) (Setting::where('group', 'postiz')->where('key', 'postiz_worker_alert_minutes')->value('value') ?? 20);
        $cutoff = now()->subMinutes($deadline);

        // Common guards: Postiz never took ownership + not already fired + overdue.
        $base = fn () => PostizPublishJob::query()
            ->whereNull('postiz_post_id')      // hand-off guard
            ->whereNull('fallback_fired_at')   // once-guard
            ->where('slot_due_at', '<=', $cutoff);

        // Group 1 — NEVER claimed (PC offline). Safe to auto-Publer / wait+alert.
        $neverClaimed = $base()
            ->where('status', PostizPublishJob::STATUS_READY)
            ->whereNull('claimed_at')
            ->get();

        // Group 2 — poller-touched & overdue (ambiguous). Operator review, NEVER auto-Publer.
        $ambiguous = $base()
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->where('status', PostizPublishJob::STATUS_CLAIMED)
                        ->where('publish_lease_until', '<', now());
                })->orWhere('status', PostizPublishJob::STATUS_FAILED);
            })
            ->get();

        $fired = 0;
        $waited = 0;
        $review = 0;

        foreach ($neverClaimed as $job) {
            $sibling = $job->sibling_type::find($job->sibling_post_id);
            $isIgVideo = $sibling !== null && $builder->siblingHasVideoMedia($sibling);
            $publerCapable = in_array($job->platform, self::PUBLER_CAPABLE, true) && ! $isIgVideo;

            if (! $publerCapable) {
                // Postiz-only (IG video carousel / Medium) — never Publer-fallback;
                // stays ready so the local node can still pick it up when back online.
                if ($job->slot_due_at <= now()->subMinutes($alertMinutes)) {
                    $waited++;
                    Log::warning('[postiz:reap-unclaimed] Postiz-only job stuck past alert threshold — local node offline?', [
                        'job_id' => $job->id,
                        'platform' => $job->platform,
                        'ig_video' => $isIgVideo,
                        'slot_due_at' => $job->slot_due_at?->toIso8601String(),
                    ]);
                }
                continue;
            }

            if ($dryRun) {
                $this->line("  [dry-run] would Publer-fallback never-claimed job #{$job->id} ({$job->platform})");
                continue;
            }

            PublishViaPubler::dispatch($job->platform, $job->sibling_post_id);
            $job->update([
                'fallback_fired_at' => now(),
                'status' => PostizPublishJob::STATUS_FAILED,
                'last_error' => 'postiz_offline_publer_fallback',
            ]);
            $fired++;
            $this->line("  → Publer fallback fired for never-claimed job #{$job->id} ({$job->platform})");
        }

        foreach ($ambiguous as $job) {
            // Could already be live on Postiz — auto-Publer here would double-post.
            $priorStatus = $job->status;
            if ($dryRun) {
                $this->line("  [dry-run] would park ambiguous job #{$job->id} ({$job->platform}) as needs_review");
                continue;
            }
            $job->update([
                'status' => PostizPublishJob::STATUS_NEEDS_REVIEW,
                'last_error' => 'postiz_ambiguous_tail_needs_review (was: ' . $priorStatus . ')',
            ]);
            $review++;
            Log::warning('[postiz:reap-unclaimed] Poller-touched job overdue — parked for operator review (NO auto-Publer, may already be live on Postiz)', [
                'job_id' => $job->id,
                'platform' => $job->platform,
                'prior_status' => $priorStatus,
                'slot_due_at' => $job->slot_due_at?->toIso8601String(),
            ]);
        }

        $total = $neverClaimed->count() + $ambiguous->count();
        if ($total > 0) {
            $this->info("Reaped {$total}: {$fired} Publer fallback, {$waited} Postiz-only waiting, {$review} parked needs_review.");
        }

        return self::SUCCESS;
    }
}
