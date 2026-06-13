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
 * Fires the Publer fallback for a Postiz job ONLY when the local node clearly
 * isn't going to publish it AND Postiz never took ownership:
 *
 *   eligible = postiz_post_id IS NULL          (async hand-off guard — never
 *                                                fall back once Postiz/Temporal
 *                                                owns it, even on later ERROR)
 *            AND fallback_fired_at IS NULL      (once-guard)
 *            AND slot_due_at <= now()-deadline  (past the grace window)
 *            AND ( ready+unclaimed/lease-expired   ← PC offline
 *                  OR claimed+lease-expired         ← PC crashed mid-claim
 *                  OR failed )                      ← pre-accepted enqueue fail
 *
 * Publer-capable platform (IG-image/TikTok/Threads/FB) → dispatch Publer.
 * IG VIDEO carousel (only Postiz can publish) + Medium → NO fallback; if stuck
 * past the alert threshold, surface a WARNING (system-level Telegram deferred,
 * same precedent as PublishSlotOrchestrator).
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

        $jobs = PostizPublishJob::query()
            ->whereNull('postiz_post_id')      // hand-off guard
            ->whereNull('fallback_fired_at')   // once-guard
            ->where('slot_due_at', '<=', $cutoff)
            ->where(function ($q) {
                // ready + (never leased OR lease expired)
                $q->where(function ($w) {
                    $w->where('status', PostizPublishJob::STATUS_READY)
                        ->where(function ($l) {
                            $l->whereNull('publish_lease_until')->orWhere('publish_lease_until', '<', now());
                        });
                })
                // claimed but lease expired (PC crashed mid-claim)
                ->orWhere(function ($w) {
                    $w->where('status', PostizPublishJob::STATUS_CLAIMED)
                        ->where('publish_lease_until', '<', now());
                })
                // pre-accepted enqueue failure (postiz_post_id NULL already guaranteed)
                ->orWhere('status', PostizPublishJob::STATUS_FAILED);
            })
            ->get();

        if ($jobs->isEmpty()) {
            return self::SUCCESS;
        }

        $fired = 0;
        $waited = 0;
        foreach ($jobs as $job) {
            $sibling = $job->sibling_type::find($job->sibling_post_id);
            $isIgVideo = $sibling !== null && $builder->siblingHasVideoMedia($sibling);
            $publerCapable = in_array($job->platform, self::PUBLER_CAPABLE, true) && ! $isIgVideo;

            if (! $publerCapable) {
                // Postiz-only (IG video carousel / Medium) — never Publer-fallback.
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
                $this->line("  [dry-run] would Publer-fallback job #{$job->id} ({$job->platform})");
                continue;
            }

            PublishViaPubler::dispatch($job->platform, $job->sibling_post_id);
            $job->update([
                'fallback_fired_at' => now(),
                'status' => PostizPublishJob::STATUS_FAILED,
                'last_error' => 'postiz_offline_publer_fallback',
            ]);
            $fired++;
            $this->line("  → Publer fallback fired for job #{$job->id} ({$job->platform})");
        }

        $this->info("Reaped {$jobs->count()} candidate(s): {$fired} fallback fired, {$waited} Postiz-only waiting.");

        return self::SUCCESS;
    }
}
