<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair (PF-2 Phase 0D). Before the StoresDatesInAppTimezone fix,
 * schedule timestamps written from an Asia/Jakarta Carbon were stored as the
 * WIB wall-clock in a UTC app, so those posts publish 7 hours late.
 *
 * Truth source: the LAST pipeline_state_log entry carrying `publish_at`
 * (ISO-8601 with offset). Only rows whose raw scheduled_at is EXACTLY 7 hours
 * after that instant are changed; anything else is listed and left alone.
 *
 * Manual only — deliberately NOT scheduled.
 *
 *   php artisan linkedin:repair-wib-schedules --dry-run
 *   php artisan linkedin:repair-wib-schedules
 */
class RepairWibScheduleTimes extends Command
{
    protected $signature = 'linkedin:repair-wib-schedules
                            {--dry-run : Print what would change without writing}';

    protected $description = 'Repair awaiting_publish LinkedIn drafts whose schedule was stored as WIB wall-clock (7 h late)';

    private const SHIFT_SECONDS = 7 * 3600;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $drafts = LinkedInPost::query()
            ->where('status', LinkedInPostStatus::AwaitingPublish->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->orderBy('id')
            ->get();

        $rows = [];
        $fixed = 0;

        foreach ($drafts as $draft) {
            $raw = Carbon::parse((string) $draft->getRawOriginal('scheduled_at'), 'UTC');
            $intended = $this->loggedPublishAt($draft);

            if ($intended === null) {
                $action = 'skip: no logged publish_at';
            } else {
                $diff = $raw->getTimestamp() - $intended->getTimestamp();
                if ($diff === self::SHIFT_SECONDS) {
                    $action = $dryRun ? 'would fix' : 'fixed';
                    if (! $dryRun) {
                        $this->fix($draft, $intended);
                        $fixed++;
                    } else {
                        $fixed++;
                    }
                } elseif ($diff === 0) {
                    $action = 'ok';
                } else {
                    $action = "skip: differs by {$diff}s";
                }
            }

            $rows[] = [
                $draft->id,
                $raw->format('Y-m-d H:i:s'),
                $intended?->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s') ?? '-',
                $action,
            ];
        }

        $this->line('id | raw | intended WIB | action');
        foreach ($rows as $row) {
            $this->line(implode(' | ', $row));
        }
        $this->info(sprintf('%s %d of %d drafts.', $dryRun ? 'Would fix' : 'Fixed', $fixed, count($rows)));

        return self::SUCCESS;
    }

    private function loggedPublishAt(LinkedInPost $draft): ?Carbon
    {
        $log = $draft->pipeline_state_log ?? [];
        for ($i = count($log) - 1; $i >= 0; $i--) {
            $value = is_array($log[$i]) ? ($log[$i]['publish_at'] ?? null) : null;
            if (is_string($value) && $value !== '') {
                try {
                    return Carbon::parse($value)->utc();
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    private function fix(LinkedInPost $draft, Carbon $intended): void
    {
        DB::transaction(function () use ($draft, $intended) {
            $draft->update([
                'scheduled_at' => $intended,
                'cancel_window_ends_at' => $intended,
            ]);

            $draft->load(['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost', 'redditPost']);
            foreach (['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost', 'redditPost'] as $rel) {
                $sibling = $draft->$rel;
                if ($sibling !== null && $sibling->getAttribute('status') !== 'published') {
                    $sibling->update(['scheduled_at' => $intended]);
                }
            }
        });
    }
}
