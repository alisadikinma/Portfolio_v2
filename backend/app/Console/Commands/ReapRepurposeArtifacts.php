<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * IG repurpose Phase G — retention reaper. Purges per-job artifact dirs under
 * storage/app/repurpose/{job}/ (downloaded source slides + caption) older than
 * N days, regardless of job status.
 *
 * Successful jobs purge their dir inline at finalize (D6); this catches the
 * failed/abandoned jobs whose dir is retained for retry/debug. Idempotent.
 *
 * Schedule: daily 04:00 WIB (ScheduledCommandSeeder row `repurpose:reap`).
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class ReapRepurposeArtifacts extends Command
{
    protected $signature = 'repurpose:reap
        {--days=7 : Purge artifact dirs older than this many days}
        {--dry-run : List what would be purged without deleting}';

    protected $description = 'Purge IG-repurpose artifact dirs older than N days (retention).';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $base = storage_path('app/repurpose');
        if (!is_dir($base)) {
            $this->info('No repurpose artifact dir — nothing to reap.');
            return self::SUCCESS;
        }

        $purged = 0;
        $kept = 0;
        foreach (File::directories($base) as $dir) {
            // mtime of the dir reflects the last write (slide download / caption).
            $mtime = Carbon::createFromTimestamp(File::lastModified($dir));
            if ($mtime->greaterThan($cutoff)) {
                $kept++;
                continue;
            }

            if ($dryRun) {
                $this->line('[dry-run] would purge: ' . $dir . ' (mtime ' . $mtime->toDateTimeString() . ')');
                $purged++;
                continue;
            }

            File::deleteDirectory($dir);
            $purged++;
        }

        $msg = sprintf('repurpose:reap done — %s %d dir(s), kept %d (cutoff %s).',
            $dryRun ? 'would purge' : 'purged', $purged, $kept, $cutoff->toDateTimeString());
        $this->info($msg);
        Log::info('[ReapRepurposeArtifacts] ' . $msg, ['days' => $days, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }
}
