<?php

namespace App\Http\Controllers\Api\Automation;

use App\Enums\LinkedInPostStatus;
use App\Enums\RepurposeJobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CaptureInstagramPost;
use App\Jobs\GenerateLinkedInPost;
use App\Models\LinkedInPost;
use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\PipelineGuard;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return 'Unknown target.';
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

        // Remaining text after stripping the URL = optional angle/instruction.
        $angle = trim(str_replace($url, '', $text));
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
        if (!in_array($action, ['blog', 'carousel'], true)) {
            return 'Unknown action.';
        }

        // Only the first tap (while still awaiting a choice) takes effect.
        if ($job->mode !== null || $job->status !== RepurposeJobStatus::Received->value) {
            return 'Already started.';
        }

        $job->update(['mode' => $action]);
        $job->transitionTo(RepurposeJobStatus::Capturing, "telegram_mode_{$action}");
        CaptureInstagramPost::dispatch($job->id);

        $label = $action === 'blog' ? '📝 Blog + Carousel' : '🎠 Carousel saja';
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
