<?php

namespace App\Console\Commands;

use App\Models\RepurposeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * IG repurpose Phase G — retention reaper. Purges per-job artifact dirs under
 * storage/app/repurpose/{job}/ (downloaded source slides + caption).
 *
 * Retention policy (June 2026): source slides are kept for the source↔generated
 * comparison and deleted N days AFTER the carousel is published (default 7d) —
 * NOT on capture date and NOT on drafting. Per dir:
 *   - linked carousel published > N days ago  → purge
 *   - linked carousel not yet published        → keep (still in review)
 *   - failed / abandoned / orphan (no job)     → purge once mtime > abandon-days
 *
 * Schedule: daily 04:00 WIB (ScheduledCommandSeeder row `repurpose:reap`).
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class ReapRepurposeArtifacts extends Command
{
    protected $signature = 'repurpose:reap
        {--days=7 : Purge a published job dir this many days after publish}
        {--abandon-days=30 : Purge an unpublished/orphan dir once its mtime is older than this}
        {--dry-run : List what would be purged without deleting}';

    protected $description = 'Purge IG-repurpose source slides N days after publish (retention).';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $abandonDays = max($days, (int) $this->option('abandon-days'));
        $dryRun = (bool) $this->option('dry-run');

        $now = Carbon::now();
        $publishCutoff = $now->copy()->subDays($days);
        $abandonCutoff = $now->copy()->subDays($abandonDays);

        $base = storage_path('app/repurpose');
        if (!is_dir($base)) {
            $this->info('No repurpose artifact dir — nothing to reap.');
            return self::SUCCESS;
        }

        $purged = 0;
        $kept = 0;
        foreach (File::directories($base) as $dir) {
            $mtime = Carbon::createFromTimestamp(File::lastModified($dir));
            $job = $this->jobForDir($dir);

            if ($this->shouldKeep($job, $mtime, $publishCutoff, $abandonCutoff)) {
                $kept++;
                continue;
            }

            if ($dryRun) {
                $this->line('[dry-run] would purge: ' . $dir);
                $purged++;
                continue;
            }

            File::deleteDirectory($dir);
            $purged++;
        }

        $msg = sprintf('repurpose:reap done — %s %d dir(s), kept %d (publish-cutoff %s, abandon-cutoff %s).',
            $dryRun ? 'would purge' : 'purged', $purged, $kept,
            $publishCutoff->toDateTimeString(), $abandonCutoff->toDateTimeString());
        $this->info($msg);
        Log::info('[ReapRepurposeArtifacts] ' . $msg, ['days' => $days, 'abandon_days' => $abandonDays, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }

    /** Resolve the RepurposeJob owning a dir (basename = id); null for orphans. */
    private function jobForDir(string $dir): ?RepurposeJob
    {
        $base = basename($dir);
        if (!ctype_digit($base)) {
            return null;
        }
        return RepurposeJob::with('linkedinPost')->find((int) $base);
    }

    /**
     * Keep a dir while its carousel is still in review, or until N days after
     * publish. Orphan / unpublished / abandoned dirs fall back to the mtime
     * abandon cutoff so storage stays bounded.
     */
    private function shouldKeep(?RepurposeJob $job, Carbon $mtime, Carbon $publishCutoff, Carbon $abandonCutoff): bool
    {
        // Orphan / legacy debris (no job row) → plain mtime retention at --days.
        if (!$job) {
            return $mtime->greaterThan($publishCutoff);
        }

        $publishedAt = $this->publishedAt($job);
        if ($publishedAt !== null) {
            // Delete N days after publish; keep until then.
            return $publishedAt->greaterThan($publishCutoff);
        }

        // Job exists but not yet published → keep for review until the abandon
        // cutoff so storage stays bounded if a draft is never published.
        return $mtime->greaterThan($abandonCutoff);
    }

    /** Publish timestamp of the linked carousel, or null when not yet published. */
    private function publishedAt(RepurposeJob $job): ?Carbon
    {
        $li = $job->linkedinPost;
        if ($li && $li->status === 'published' && $li->published_at) {
            return Carbon::parse($li->published_at);
        }
        return null;
    }
}
