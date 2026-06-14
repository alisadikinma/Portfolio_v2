<?php

namespace App\Http\Controllers\Api\Automation;

use App\Enums\LinkedInPostStatus;
use App\Enums\RepurposeJobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CaptureInstagramPost;
use App\Jobs\CaptureVideoCarousel;
use App\Jobs\GenerateLinkedInPost;
use App\Jobs\ParseAndScheduleReply;
use App\Models\LinkedInPost;
use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\LinkedInFixedSlotScheduler;
use App\Services\LinkedInSchedulingService;
use App\Services\PipelineGuard;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase F of docs/plans/2026-05-07-pipeline-error-classifier-and-bounded-retry.md
 *
 * Inbound Telegram webhook for inline-button callbacks. Telegram pings this
 * URL when the operator taps a button on a notification message; we verify
 * the request came from Telegram (via secret header), verify the callback
 * payload was signed by us (via HMAC), then dispatch the appropriate retry
 * or cancel action.
 *
 * Two-layer auth:
 *   1. `X-Telegram-Bot-Api-Secret-Token` header must match the webhook
 *      secret we set via setWebhook (proves Telegram → our server identity)
 *   2. `callback_data` HMAC must verify against the same secret (proves
 *      the button text was issued by us, not forged by a third party
 *      who somehow got the chat ID)
 *
 * Returns 200 OK in nearly all paths (Telegram retries on 5xx). Returns
 * 403 only when the secret token header is missing/wrong — that signals
 * a misconfigured webhook URL or impersonation attempt and we want
 * Telegram to stop sending.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, PipelineGuard $guard): JsonResponse
    {
        $secret = (string) Setting::where('group', 'telegram')
            ->where('key', 'telegram_webhook_secret')
            ->value('value');

        if ($secret === '') {
            Log::warning('[TelegramWebhook] secret not configured — rejecting');
            return response()->json(['ok' => false, 'error' => 'webhook_not_configured'], 403);
        }

        $headerSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if (!hash_equals($secret, $headerSecret)) {
            Log::warning('[TelegramWebhook] secret mismatch', [
                'header_present' => $headerSecret !== '',
            ]);
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        // Telegram payload shape varies. Button taps arrive as callback_query;
        // plain text (e.g. an IG repurpose URL) arrives as message.
        $callbackQuery = $request->input('callback_query');
        if (!is_array($callbackQuery)) {
            $message = $request->input('message');
            if (is_array($message)) {
                $this->handleMessage($message);
            }
            // Acknowledge so Telegram doesn't retry (channel posts, /start, etc.).
            return response()->json(['ok' => true]);
        }

        $callbackData = (string) ($callbackQuery['data'] ?? '');
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $verified = TelegramNotificationService::verifyCallback($callbackData, $secret);

        if ($verified === null) {
            Log::info('[TelegramWebhook] callback_data verify failed', [
                'data_len' => strlen($callbackData),
            ]);
            $this->answerCallbackQuery($callbackId, 'Invalid or expired button.');
            return response()->json(['ok' => true]);
        }

        try {
            $actionResponse = $this->dispatchAction($verified, $guard);
        } catch (\Throwable $e) {
            Log::error('[TelegramWebhook] action dispatch threw', [
                'verified' => $verified,
                'error' => $e->getMessage(),
            ]);
            $this->answerCallbackQuery($callbackId, '⚠️ Action failed; check admin.');
            return response()->json(['ok' => true]);
        }

        $this->answerCallbackQuery($callbackId, $actionResponse);
        return response()->json(['ok' => true]);
    }

    /**
     * Apply the operator's chosen action. Idempotent — calling twice
     * with the same payload only mutates state once.
     */
    private function dispatchAction(array $verified, PipelineGuard $guard): string
    {
        $action = $verified['action'];
        $kind = $verified['kind'];
        $id = $verified['id'];

        if ($kind === 'linkedin') {
            $draft = LinkedInPost::find($id);
            if (!$draft) {
                return 'Draft not found.';
            }
            return $this->resolveLinkedInAction($draft, $action, $guard);
        }

        if ($kind === 'idea') {
            // Content Engine retries flow through AutoPipelineOrchestrator's
            // retryReadyIdeas — set pipeline_next_retry_at to now so the
            // next tick picks it up. Cancel = no-op (operator handles via
            // admin UI for ideas).
            $idea = \App\Models\ContentIdea::find($id);
            if (!$idea) {
                return 'Idea not found.';
            }
            return $this->resolveIdeaAction($idea, $action);
        }

        if ($kind === 'repurpose') {
            $job = RepurposeJob::find($id);
            if (!$job) {
                return 'Repurpose job not found.';
            }
            return $this->resolveRepurposeAction($job, $action);
        }

        if ($kind === 'schedule') {
            return $this->resolveScheduleAction($id, $action);
        }

        return 'Unknown target.';
    }

    /**
     * Apply a scheduling-conversation button tap (Phase F). Actions:
     *   - slot0|slot1|slot2 → schedule at candidate_slots[i] from the cache state
     *     (already weekend/holiday/occupancy-filtered → no conflict re-check)
     *   - confirm → schedule the proposed_slot despite a ±30-min collision
     *   - reject → re-offer 3 fresh suggestions
     * Conversation state lives in cache `telegram_schedule_state:{chat}`.
     */
    private function resolveScheduleAction(int $draftId, string $action): string
    {
        $chatId = (string) Setting::where('group', 'telegram')
            ->where('key', 'telegram_chat_id')
            ->value('value');
        $stateKey = "telegram_schedule_state:{$chatId}";
        $state = Cache::get($stateKey);

        $draft = LinkedInPost::find($draftId);
        if (!$draft) {
            // Draft deleted mid-conversation — clear the stale lock so the next
            // ready draft can be prompted (mirrors ParseAndScheduleReply).
            Cache::forget($stateKey);
            return 'Draft not found.';
        }

        $scheduling = app(LinkedInSchedulingService::class);
        $telegram = app(TelegramNotificationService::class);

        if (str_starts_with($action, 'slot')) {
            if (!is_array($state) || (int) ($state['draft_id'] ?? 0) !== $draftId) {
                return 'Prompt kedaluwarsa — tunggu prompt berikutnya.';
            }
            $i = (int) substr($action, 4);
            $iso = $state['candidate_slots'][$i] ?? null;
            if (!is_string($iso)) {
                return 'Slot tidak valid.';
            }
            $slot = Carbon::parse($iso);
            $scheduling->scheduleAt($draft, $slot, 'telegram_slot_button');
            Cache::forget($stateKey);
            $telegram->sendScheduleConfirmed($draft, $slot);

            return '✅ Dijadwalkan ' . $slot->locale('id')->isoFormat('ddd, D MMM HH:mm') . ' WIB.';
        }

        if ($action === 'confirm') {
            if (!is_array($state)
                || ($state['step'] ?? null) !== 'awaiting_conflict_confirm'
                || (int) ($state['draft_id'] ?? 0) !== $draftId
                || !is_string($state['proposed_slot'] ?? null)) {
                return 'Tidak ada konfirmasi tertunda.';
            }
            $slot = Carbon::parse($state['proposed_slot']);
            $scheduling->scheduleAt($draft, $slot, 'telegram_conflict_override');
            Cache::forget($stateKey);
            $telegram->sendScheduleConfirmed($draft, $slot);

            return '✅ Dijadwalkan (override).';
        }

        if ($action === 'reject') {
            try {
                $slots = app(LinkedInFixedSlotScheduler::class)->nextAvailableSlots(3);
            } catch (\Throwable $e) {
                return 'Tidak ada slot kosong dalam jendela.';
            }
            Cache::put($stateKey, [
                'draft_id' => $draftId,
                'step' => 'awaiting_datetime',
                'candidate_slots' => array_map(fn ($s) => $s->toIso8601String(), $slots),
            ], now()->addMinutes(60));
            $telegram->sendSchedulePrompt($draft, $slots);

            return 'Oke — pilih slot lain atau ketik sendiri.';
        }

        return 'Unknown action.';
    }

    private function resolveLinkedInAction(LinkedInPost $draft, string $action, PipelineGuard $guard): string
    {
        if ($action === 'retry') {
            // Idempotency — if already advanced past Failed, no-op.
            if ($draft->status !== LinkedInPostStatus::Failed->value) {
                return 'Already in progress.';
            }
            $guard->advance(
                $draft,
                LinkedInPostStatus::PendingGeneration,
                'telegram_inline_button_retry'
            );
            GenerateLinkedInPost::dispatch($draft->id);
            return '🔁 Retry dispatched.';
        }

        if ($action === 'cancel') {
            if (in_array($draft->status, [
                LinkedInPostStatus::Cancelled->value,
                LinkedInPostStatus::Published->value,
            ], true)) {
                return 'Already in terminal state.';
            }
            $guard->advance(
                $draft,
                LinkedInPostStatus::Cancelled,
                'telegram_inline_button_cancel'
            );
            return '✕ Draft cancelled.';
        }

        return 'Unknown action.';
    }

    private function resolveIdeaAction(\App\Models\ContentIdea $idea, string $action): string
    {
        if ($action === 'retry') {
            if ($idea->status !== 'failed') {
                return 'Already in progress.';
            }
            // Schedule for immediate retry — AutoPipelineOrchestrator picks
            // up rows where pipeline_next_retry_at <= now() on its next tick
            // (every minute via content:auto-pipeline).
            $idea->update([
                'pipeline_next_retry_at' => now(),
            ]);
            return '🔁 Retry scheduled for next pipeline tick.';
        }

        if ($action === 'cancel') {
            // ContentIdea has no FSM=Cancelled; archive instead.
            $idea->update(['status' => 'archived']);
            return '✕ Idea archived.';
        }

        return 'Unknown action.';
    }

    /**
     * Inbound text message handler — IG repurpose entrypoint. When the operator
     * sends an Instagram post/reel URL from the allowed chat (and the feature is
     * enabled), create a RepurposeJob and reply with mode-choice buttons (D9).
     * The capture pipeline starts only after a button is tapped
     * (resolveRepurposeAction). Silently ignores non-IG text / wrong chat.
     */
    private function handleMessage(array $message): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            return;
        }

        $incomingChatId = (string) ($message['chat']['id'] ?? '');
        $allowedChatId = (string) Setting::where('group', 'telegram')
            ->where('key', 'telegram_chat_id')
            ->value('value');
        $chatAllowed = $allowedChatId !== '' && hash_equals($allowedChatId, $incomingChatId);

        // Scheduling-conversation free-text reply (Phase F) — handled BEFORE the
        // repurpose gate so it works regardless of telegram_repurpose_enabled.
        // When a "kapan posting?" prompt is awaiting a datetime for THIS chat and
        // the message isn't an IG URL, hand it to the queued Claude-CLI parser
        // (webhook stays fast). The parse job validates weekday/holiday/conflict.
        if ($chatAllowed) {
            $state = Cache::get("telegram_schedule_state:{$incomingChatId}");
            if (is_array($state)
                && ($state['step'] ?? null) === 'awaiting_datetime'
                && $this->extractInstagramUrl($text) === null) {
                ParseAndScheduleReply::dispatch($incomingChatId, (int) ($state['draft_id'] ?? 0), $text);
                return;
            }
        }

        // Feature toggle.
        $enabled = (string) Setting::where('group', 'telegram')
            ->where('key', 'telegram_repurpose_enabled')
            ->value('value');
        if ($enabled !== 'true') {
            return;
        }

        // Allowlist: only the configured operator chat may trigger repurpose.
        $allowedChatId = (string) Setting::where('group', 'telegram')
            ->where('key', 'telegram_chat_id')
            ->value('value');
        $incomingChatId = (string) ($message['chat']['id'] ?? '');
        if ($allowedChatId === '' || !hash_equals($allowedChatId, $incomingChatId)) {
            Log::info('[TelegramRepurpose] message from non-allowed chat ignored', [
                'chat_present' => $incomingChatId !== '',
            ]);
            return;
        }

        // SSRF guard: only accept instagram.com post/reel/tv URLs.
        $url = $this->extractInstagramUrl($text);
        if ($url === null) {
            return; // non-IG text — ignore quietly, no spam reply
        }

        // Remaining text after stripping the FULL URL token (incl. the
        // ?img_index/?igsh query string IG appends to share links) = optional
        // angle/instruction. Stripping only the matched clean URL would leave
        // the query string behind and misread it as an angle — and the `_` in
        // `img_index` then broke the Markdown mode-prompt (Telegram 400).
        $angle = trim(preg_replace('~https?://\S+~i', '', $text));
        $angle = $angle !== '' ? $angle : null;

        $job = RepurposeJob::create([
            'source_url' => $url,
            'angle' => $angle,
            'mode' => null,
            'status' => RepurposeJobStatus::Received->value,
            'chat_id' => $incomingChatId,
        ]);

        app(TelegramNotificationService::class)->sendRepurposeModePrompt($job);
    }

    /**
     * Extract the first instagram.com post/reel/tv URL from text. Host-restricted
     * (no arbitrary host) so the downstream Playwright capture can never be
     * pointed at a non-Instagram target.
     */
    private function extractInstagramUrl(string $text): ?string
    {
        $pattern = '~https?://(?:www\.)?instagram\.com/(?:p|reel|reels|tv)/[A-Za-z0-9_-]+/?~i';
        if (!preg_match($pattern, $text, $m)) {
            return null;
        }
        return $m[0];
    }

    /**
     * Apply the operator's mode choice (blog | carousel). Sets RepurposeJob.mode,
     * advances FSM received → capturing, and dispatches the capture job.
     * Idempotent — a late second tap after the pipeline has started is a no-op.
     */
    private function resolveRepurposeAction(RepurposeJob $job, string $action): string
    {
        // A5: the exhaustion-alert inline Retry button re-runs a Failed job through
        // the SAME path as the admin HTTP endpoint (RepurposeRetryService). Idempotent
        // — the service no-ops when the job isn't Failed.
        if ($action === 'retry') {
            $result = app(\App\Services\RepurposeRetryService::class)->retry($job);

            return $result['ok'] ? '🔁 Retry dispatched.' : $result['message'];
        }

        if (!in_array($action, ['blog', 'carousel', 'video_rebrand'], true)) {
            return 'Unknown action.';
        }

        // Only the first tap (while still awaiting a choice) takes effect.
        if ($job->mode !== null || $job->status !== RepurposeJobStatus::Received->value) {
            return 'Already started.';
        }

        $job->update(['mode' => $action]);
        $job->transitionTo(RepurposeJobStatus::Capturing, "telegram_mode_{$action}");

        // video_rebrand re-skins a VIDEO carousel (yt-dlp download path), the other
        // two modes scrape image slides (Playwright). Different capture job per mode.
        if ($action === 'video_rebrand') {
            CaptureVideoCarousel::dispatch($job->id);
        } else {
            CaptureInstagramPost::dispatch($job->id);
        }

        $label = match ($action) {
            'blog' => '📝 Blog + Carousel',
            'video_rebrand' => '🎬 Video rebrand',
            default => '🎠 Carousel saja',
        };

        // Persistent chat bubble so the operator sees the pipeline actually
        // started — the answerCallbackQuery toast is transient and easy to miss.
        // Best-effort: a notify failure must never undo the dispatch above.
        try {
            app(TelegramNotificationService::class)
                ->sendRepurposeProgress($job, "✅ {$label} dipilih — mulai memproses…");
        } catch (\Throwable $e) {
            Log::warning('[TelegramRepurpose] progress ack failed', [
                'job' => $job->id,
                'error' => $e->getMessage(),
            ]);
        }

        return "⏳ {$label} repurpose started.";
    }

    /**
     * Reply to the operator's tap with a small toast in the Telegram client.
     * Failure here is non-fatal — the action already mutated state.
     */
    private function answerCallbackQuery(string $callbackId, string $text): void
    {
        if ($callbackId === '') {
            return;
        }
        $token = (string) Setting::where('group', 'telegram')
            ->where('key', 'telegram_bot_token')
            ->value('value');
        if ($token === '') {
            return;
        }

        try {
            Http::timeout(5)->post(
                'https://api.telegram.org/bot' . $token . '/answerCallbackQuery',
                [
                    'callback_query_id' => $callbackId,
                    'text' => mb_substr($text, 0, 200),
                ]
            );
        } catch (\Throwable $e) {
            Log::info('[TelegramWebhook] answerCallbackQuery failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
