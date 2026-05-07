<?php

namespace App\Http\Controllers\Api\Automation;

use App\Enums\LinkedInPostStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateLinkedInPost;
use App\Models\LinkedInPost;
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

        // Telegram payload shape varies; we only act on callback_query.
        $callbackQuery = $request->input('callback_query');
        if (!is_array($callbackQuery)) {
            // Could be a /start text message, channel post, etc. Acknowledge
            // and move on so Telegram doesn't retry.
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
            $message = $this->dispatchAction($verified, $guard);
        } catch (\Throwable $e) {
            Log::error('[TelegramWebhook] action dispatch threw', [
                'verified' => $verified,
                'error' => $e->getMessage(),
            ]);
            $this->answerCallbackQuery($callbackId, '⚠️ Action failed; check admin.');
            return response()->json(['ok' => true]);
        }

        $this->answerCallbackQuery($callbackId, $message);
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
