<?php

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Jobs\DispatchTelegramNotification;
use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Services\LinkedInPublishService;
use App\Services\PipelineGuard;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Publishes awaiting_publish drafts whose cancel window has elapsed.
 *
 * Runs every minute via routes/console.php schedule. Three outcomes per row:
 *   1. kill-switch OFF (linkedin_auto_publish=false) → demote to manual_review
 *      with reason=kill_switch_demotion
 *   2. kill-switch ON + publish success → status=Published + URN/URL stored
 *   3. kill-switch ON + publish failure → status=Failed with last_error
 *
 * Telegram notifications (opt-in via telegram_notify_linkedin_published +
 * telegram_notify_linkedin_depth_failed) fire after status transitions.
 */
class ProcessScheduledLinkedInPosts extends Command
{
    protected $signature = 'linkedin:process-scheduled';
    protected $description = 'Publish LinkedIn drafts whose cancel window has elapsed (awaiting_publish → published)';

    public function handle(LinkedInPublishService $publisher, PipelineGuard $guard, TelegramNotificationService $telegram): int
    {
        $due = LinkedInPost::where('status', LinkedInPostStatus::AwaitingPublish->value)
            ->whereNotNull('cancel_window_ends_at')
            ->where('cancel_window_ends_at', '<=', now())
            ->with('account')
            ->get();

        if ($due->isEmpty()) {
            return 0;
        }

        $this->info("Found {$due->count()} LinkedIn drafts ready to publish.");

        $autoPublishSetting = Setting::get('linkedin_auto_publish', config('linkedin.auto_publish', false));
        $autoPublish = filter_var($autoPublishSetting, FILTER_VALIDATE_BOOLEAN);

        foreach ($due as $draft) {
            if (!$autoPublish) {
                $this->line("  #{$draft->id} — kill switch OFF, demoting to manual_review");
                try {
                    $guard->advance(
                        $draft,
                        LinkedInPostStatus::ManualReview,
                        'kill_switch_demotion'
                    );
                } catch (\Throwable $e) {
                    Log::error('[LinkedInScheduler] Demotion failed', [
                        'draft_id' => $draft->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                continue;
            }

            $this->line("  #{$draft->id} — publishing (format={$draft->format})");
            $result = $publisher->publish($draft);

            if (!$result['success']) {
                $this->error("    publish failed: {$result['error']}");
                Log::warning('[LinkedInScheduler] Publish failed', [
                    'draft_id' => $draft->id,
                    'error' => $result['error'] ?? null,
                ]);

                try {
                    $guard->advance(
                        $draft,
                        LinkedInPostStatus::Failed,
                        'publish_error',
                        ['error' => $result['error'] ?? 'unknown']
                    );
                    $draft->update(['last_error' => $result['error'] ?? 'Publish failed']);
                } catch (\Throwable $e) {
                    Log::error('[LinkedInScheduler] Could not mark failed', [
                        'draft_id' => $draft->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                continue;
            }

            try {
                $guard->advance($draft, LinkedInPostStatus::Published, 'scheduler_auto_publish', [
                    'post_urn' => $result['post_urn'] ?? null,
                ]);
                $draft->update([
                    'published_at' => now(),
                    'linkedin_post_urn' => $result['post_urn'] ?? null,
                    'linkedin_post_url' => $result['post_url'] ?? null,
                ]);
                $this->info("    published: {$result['post_url']}");

                try {
                    $telegram->sendLinkedInPublishSuccess($draft->fresh(['post.translations']));
                } catch (\Throwable $e) {
                    Log::warning('[LinkedInScheduler] Telegram publish notification threw', [
                        'draft_id' => $draft->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('[LinkedInScheduler] Could not mark published', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return 0;
    }
}
