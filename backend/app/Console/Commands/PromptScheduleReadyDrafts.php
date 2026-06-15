<?php

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Exceptions\NoAvailableSlotException;
use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Services\LinkedInFixedSlotScheduler;
use App\Services\LinkedInSlotReadinessService;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase E — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * Sends ONE Telegram "kapan posting?" prompt for the next genuinely-ready,
 * unscheduled LinkedIn draft so the operator picks the publish slot
 * (human-in-the-loop). Gated by linkedin_telegram_schedule_enabled — when off,
 * no-op (the auto-schedule cron owns scheduling instead).
 *
 * One prompt per draft (no reminder): stamps schedule_prompt_sent_at, and only
 * AFTER a successful Telegram send so a failed send doesn't burn the one-shot.
 * Serialized: at most one open conversation per chat (telegram_schedule_state
 * cache key) so a free-text reply is never ambiguous.
 */
class PromptScheduleReadyDrafts extends Command
{
    protected $signature = 'linkedin:prompt-schedule {--dry-run}';

    protected $description = 'Telegram prompt the operator to schedule the next ready unscheduled LinkedIn draft';

    public function handle(
        LinkedInSlotReadinessService $readiness,
        TelegramNotificationService $telegram,
    ): int {
        if ($this->setting('linkedin', 'linkedin_telegram_schedule_enabled') !== 'true') {
            $this->info('[linkedin:prompt-schedule] skipped: linkedin_telegram_schedule_enabled != true');
            return self::SUCCESS;
        }

        $chatId = (string) $this->setting('telegram', 'telegram_chat_id');
        if ($chatId === '') {
            Log::warning('[linkedin:prompt-schedule] no telegram_chat_id configured');
            return self::SUCCESS;
        }

        // Serialize: only one open scheduling conversation per chat. The next
        // ready draft waits until this one resolves (or the state TTL expires).
        if (Cache::has("telegram_schedule_state:{$chatId}")) {
            $this->info('[linkedin:prompt-schedule] skipped: a scheduling conversation is already open');
            return self::SUCCESS;
        }

        $candidates = LinkedInPost::query()
            ->excludeVideoCarousel() // IG-video anchors are scheduled via Zernio, not the Telegram prompt
            ->where('status', LinkedInPostStatus::ManualReview->value)
            ->whereNull('scheduled_at')
            ->whereNull('schedule_prompt_sent_at')
            ->whereNull('deleted_at')
            ->with(['post.translations', 'instagramPost', 'tiktokPost', 'threadsPost'])
            ->orderBy('updated_at')
            ->get();

        foreach ($candidates as $draft) {
            if (! $this->isReady($draft, $readiness)) {
                continue;
            }

            try {
                $slots = app(LinkedInFixedSlotScheduler::class)->nextAvailableSlots(3);
            } catch (NoAvailableSlotException $e) {
                Log::warning('[linkedin:prompt-schedule] no available slots in lookahead window', [
                    'draft_id' => $draft->id,
                ]);
                return self::SUCCESS;
            }

            if ($this->option('dry-run')) {
                $this->info(sprintf(
                    '[dry-run] would prompt draft %d with slots: %s',
                    $draft->id,
                    implode(', ', array_map(fn ($s) => $s->toIso8601String(), $slots))
                ));
                return self::SUCCESS;
            }

            // Acquire the per-chat conversation lock atomically (put-if-absent)
            // BEFORE sending, so a process racing past the Cache::has fast-path
            // above (e.g. a manual artisan run alongside the cron) can't open a
            // second conversation. Cache::add returns false if one already exists.
            $acquired = Cache::add("telegram_schedule_state:{$chatId}", [
                'draft_id' => $draft->id,
                'step' => 'awaiting_datetime',
                'candidate_slots' => array_map(fn ($s) => $s->toIso8601String(), $slots),
            ], now()->addMinutes(60));
            if (! $acquired) {
                $this->info('[linkedin:prompt-schedule] skipped: conversation opened concurrently');
                return self::SUCCESS;
            }

            // Only consume the one-shot on a successful send — a failed Telegram
            // delivery must not suppress the (only) prompt this draft ever gets.
            // Release the lock on failure so the next tick retries.
            if (! $telegram->sendSchedulePrompt($draft, $slots)) {
                Cache::forget("telegram_schedule_state:{$chatId}");
                Log::warning('[linkedin:prompt-schedule] Telegram send failed; not consuming one-shot', [
                    'draft_id' => $draft->id,
                ]);
                return self::SUCCESS;
            }

            $draft->update(['schedule_prompt_sent_at' => now()]);
            $this->info("[linkedin:prompt-schedule] prompted draft {$draft->id}");

            return self::SUCCESS; // one conversation per run
        }

        $this->info('[linkedin:prompt-schedule] no ready unscheduled drafts');
        return self::SUCCESS;
    }

    /**
     * Genuinely-ready gate. Carousel: every slide rendered AND every configured
     * cross-post caption ready. Text: a manual_review text draft already passed
     * validation, so it's ready.
     */
    private function isReady(LinkedInPost $draft, LinkedInSlotReadinessService $readiness): bool
    {
        if ($draft->format !== 'carousel') {
            return true;
        }

        $slides = $draft->carousel_slides ?? [];
        if (empty($slides)) {
            return false;
        }

        $allRendered = collect($slides)->every(
            fn ($s) => ($s['image_status'] ?? null) === 'done' && ! empty($s['image_url'])
        );
        if (! $allRendered) {
            return false;
        }

        return ($readiness->captionReadinessForApproval($draft)['ready'] ?? false) === true;
    }

    private function setting(string $group, string $key): ?string
    {
        $value = Setting::where('group', $group)->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }
}
