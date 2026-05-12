<?php

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use App\Services\PipelineGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Marks LinkedIn drafts stuck in `generating` or `validating` for longer
 * than the SSH timeout budget as `failed`.
 *
 * Without this reaper, any SSH timeout, queue worker crash, or PHP fatal
 * mid-generation leaves the draft in `generating` forever — the queue
 * retry path silent-skips rows whose status has already advanced past
 * `pending_generation` (see GenerateLinkedInPost::handle and the
 * "already in progress" guard in LinkedInGenerationService::generate).
 *
 * Threshold default 25 minutes — comfortably above the worst-case
 * 2 × 600s SSH budget (initial /linkedin-gen + format-mix governor
 * re-dispatch, see LinkedInGenerationService line 180) plus a 60s
 * job-timeout margin = 21min wall. Slow-but-live generations are NOT
 * clobbered. Mirror of GenerateLinkedInPost::STALE_THRESHOLD_MINUTES.
 *
 * Action: reaped rows transition to `failed` with a clear `last_error`
 * stamp. Operator can then click "Restart from blog" in admin UI to
 * dispatch a fresh draft. (We deliberately don't auto-redispatch —
 * repeated automatic retries would mask SSH/plugin issues; surfacing
 * to admin keeps the human in the loop.)
 */
class ReapStuckLinkedInPosts extends Command
{
    protected $signature = 'linkedin:reap-stuck
        {--threshold=25 : Minutes after which generating/validating is considered stuck}
        {--dry-run : Log candidates without mutating}';

    protected $description = 'Mark stuck generating/validating LinkedIn drafts as failed (recovery for SSH timeouts and queue crashes)';

    public function handle(PipelineGuard $guard): int
    {
        $threshold = max(15, (int) $this->option('threshold'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes($threshold);

        $stuck = LinkedInPost::whereIn('status', [
            LinkedInPostStatus::Generating->value,
            LinkedInPostStatus::Validating->value,
        ])
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($stuck->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("Found {$stuck->count()} stuck LinkedIn draft(s) past {$threshold}m threshold.");

        $reaped = 0;
        foreach ($stuck as $draft) {
            $age = (int) round($draft->updated_at?->diffInMinutes(now()) ?? 0);
            $reason = "Reaper: stuck in {$draft->status} for {$age}m (threshold={$threshold}m)";

            $this->line("  #{$draft->id} status={$draft->status} age={$age}m");

            if ($dryRun) {
                continue;
            }

            try {
                $guard->advance(
                    $draft,
                    LinkedInPostStatus::Failed,
                    'reaped_stuck',
                    [
                        'previous_status' => $draft->status,
                        'age_minutes' => $age,
                        'threshold_minutes' => $threshold,
                    ]
                );
                $draft->update(['last_error' => $reason]);
                $reaped++;
            } catch (\Throwable $e) {
                $this->error("    failed to reap #{$draft->id}: {$e->getMessage()}");
                Log::error('[LinkedInReaper] Could not reap stuck draft', [
                    'draft_id' => $draft->id,
                    'status' => $draft->status,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($dryRun
            ? "Dry run — would have reaped {$stuck->count()}."
            : "Reaped {$reaped} draft(s)."
        );

        return self::SUCCESS;
    }
}
