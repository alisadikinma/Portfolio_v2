<?php

namespace App\Services;

use App\Models\ContentIdea;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends operational alerts to the admin's personal Telegram via the Bot API.
 * All sends are gated by the telegram_enabled master toggle plus a per-type
 * toggle. Token + chat_id are read from the telegram Settings group, set via
 * the AboutSettings admin UI. No-op when either token or chat_id is missing.
 */
class TelegramNotificationService
{
    private const API_BASE = 'https://api.telegram.org/bot';
    private const REQUEST_TIMEOUT = 10;

    /**
     * Send a manual-upload alert when /article-images Phase 3.5b detects named
     * entities that couldn't be auto-fetched from Wikimedia (low notability,
     * license fail, or API error). The admin must manually upload references
     * via the Gate 2 UI before GeminiGen can dispatch.
     */
    public function sendManifestAlert(ContentIdea $idea): bool
    {
        if (!$this->isEnabledFor('manifest_needed')) {
            return false;
        }

        $manifest = $idea->pending_manifest ?? [];
        $entities = $manifest['entity'] ?? [];

        $missing = array_filter($entities, fn ($e) => is_array($e) && ($e['status'] ?? null) === 'missing');
        $fetched = array_filter($entities, fn ($e) => is_array($e) && ($e['status'] ?? null) === 'fetched');

        $lines = [];
        $lines[] = '🚨 *Manual upload needed* — Content Engine';
        $lines[] = '';
        $lines[] = 'Article: _' . $this->escapeMarkdown($idea->title ?? 'Untitled') . '_';
        $lines[] = '';

        if (!empty($missing)) {
            $lines[] = '*Missing references:*';
            foreach ($missing as $e) {
                $icon = $this->entityIcon($e['entity_type'] ?? null);
                $name = $e['entity_name'] ?? 'Unknown';
                $reason = $e['reason'] ?? 'reference unavailable';
                $lines[] = "• {$icon} " . $this->escapeMarkdown($name) . " — " . $this->escapeMarkdown($reason);
            }
            $lines[] = '';
        }

        if (!empty($fetched)) {
            $lines[] = '*Auto-fetched:*';
            foreach ($fetched as $e) {
                $icon = $this->entityIcon($e['entity_type'] ?? null);
                $name = $e['entity_name'] ?? 'Unknown';
                $license = $e['license'] ?? 'CC';
                $lines[] = "• {$icon} " . $this->escapeMarkdown($name) . " — " . $this->escapeMarkdown($license);
            }
            $lines[] = '';
        }

        $adminUrl = $this->buildAdminUrl($idea);
        $lines[] = "[Open Admin]({$adminUrl})";

        return $this->send(implode("\n", $lines), [
            'inline_keyboard' => [[
                ['text' => '📤 Open Upload UI', 'url' => $adminUrl],
            ]],
        ]);
    }

    /**
     * Alert on GeminiGen image generation failure (any segment).
     */
    public function sendGenerationFailed(ContentIdea $idea): bool
    {
        if (!$this->isEnabledFor('generation_failed')) {
            return false;
        }

        $text = "❌ *Image generation failed* — Content Engine\n\n"
            . 'Article: _' . $this->escapeMarkdown($idea->title ?? 'Untitled') . "_\n\n"
            . "Check admin logs + retry, or reject the idea.\n\n"
            . "[Open Admin](" . $this->buildAdminUrl($idea) . ")";

        return $this->send($text);
    }

    /**
     * Alert when a single image segment exhausts retry attempts (terminal failure).
     * Includes segment index + last failure reason so the admin can decide to
     * retry manually, skip the segment, or abandon the idea.
     */
    public function sendSegmentRetryExhausted(ContentIdea $idea, int $segmentIndex): bool
    {
        if (!$this->isEnabledFor('segment_failed')) {
            return false;
        }

        $segment = $idea->generated_article['image_prompts'][$segmentIndex] ?? [];
        $vd = $segment['visual_direction'] ?? ($segment['prompt_text'] ?? '');
        $history = $segment['failure_history'] ?? [];
        $lastReason = !empty($history) ? (end($history)['reason'] ?? 'unknown') : 'unknown';

        $lines = [];
        $lines[] = '⚠️ *Segment retry exhausted* — Content Engine';
        $lines[] = '';
        $lines[] = 'Article: _' . $this->escapeMarkdown($idea->title ?? 'Untitled') . '_';
        $lines[] = 'Segment: #' . $segmentIndex;
        if ($vd !== '') {
            $lines[] = 'Direction: _' . $this->escapeMarkdown(\Illuminate\Support\Str::limit($vd, 140)) . '_';
        }
        $lines[] = 'Last failure: ' . $this->escapeMarkdown(\Illuminate\Support\Str::limit($lastReason, 200));
        $lines[] = '';
        $lines[] = '[Open Admin](' . $this->buildAdminUrl($idea) . ')';

        return $this->send(implode("\n", $lines));
    }

    /**
     * Alert when the cover segment (index 0) fails terminally. Cover is
     * critical — idea cannot advance to images_ready without a usable cover
     * image. Operator must retry, upload manually, or abandon.
     */
    public function sendCoverCriticalAlert(ContentIdea $idea): bool
    {
        if (!$this->isEnabledFor('cover_critical')) {
            return false;
        }

        $text = "🛑 *Cover image critical failure* — Content Engine\n\n"
            . 'Article: _' . $this->escapeMarkdown($idea->title ?? 'Untitled') . "_\n\n"
            . "The cover segment (index 0) hit terminal failure. This idea is blocked "
            . "at `generating_images` until the cover is retried, manually uploaded, or skipped.\n\n"
            . "[Open Admin](" . $this->buildAdminUrl($idea) . ")";

        return $this->send($text);
    }

    /**
     * Alert when the cron auto-pipeline exhausts translation retries and falls
     * back to publishing monolingual. Operator can manually re-run translate
     * via the existing admin Gate 2 endpoint.
     */
    public function sendAutoTranslateExhausted(ContentIdea $idea): bool
    {
        if (!$this->isEnabledFor('translate_failed')) {
            return false;
        }

        $text = "🌐 *Auto-translate exhausted* — Content Engine\n\n"
            . 'Article: _' . $this->escapeMarkdown($idea->title ?? 'Untitled') . "_\n\n"
            . "Published monolingual. Translation retries hit the cap — "
            . "re-run translation manually from the admin UI if needed.\n\n"
            . "[Open Admin](" . $this->buildAdminUrl($idea) . ")";

        return $this->send($text);
    }

    /**
     * Celebratory alert on successful article publish.
     */
    public function sendPublishSuccess(ContentIdea $idea): bool
    {
        if (!$this->isEnabledFor('publish_success')) {
            return false;
        }

        $text = "✅ *Published* — Content Engine\n\n"
            . 'Article: _' . $this->escapeMarkdown($idea->title ?? 'Untitled') . "_\n\n"
            . "[View on site](" . config('app.url') . "/blog)";

        return $this->send($text);
    }

    /**
     * Send a test message using the current configured token + chat_id.
     * Does NOT check the per-type notification toggles (this is a config test).
     * Still respects the master telegram_enabled toggle via the token check.
     */
    public function sendTestMessage(): array
    {
        $token = $this->getBotToken();
        $chatId = $this->getChatId();

        if (empty($token) || empty($chatId)) {
            return [
                'success' => false,
                'error' => 'Bot token or chat ID not configured',
            ];
        }

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->post(self::API_BASE . $token . '/sendMessage', [
                    'chat_id' => $chatId,
                    'parse_mode' => 'Markdown',
                    'text' => "✅ *Test message* from alisadikinma.com Content Engine.\n\nIf you see this, Telegram notifications are correctly configured.",
                ]);

            return [
                'success' => $response->successful(),
                'telegram_response' => $response->json(),
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function isEnabledFor(string $notificationType): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }

        $toggleKey = 'telegram_notify_' . $notificationType;
        if ($this->getSetting($toggleKey) !== 'true') {
            return false;
        }

        // Master gate: token + chat_id must both be set for any send
        return !empty($this->getBotToken()) && !empty($this->getChatId());
    }

    private function send(string $text, ?array $replyMarkup = null): bool
    {
        $token = $this->getBotToken();
        $chatId = $this->getChatId();

        if (empty($token) || empty($chatId)) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'parse_mode' => 'Markdown',
            'text' => $text,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->post(self::API_BASE . $token . '/sendMessage', $payload);

            if (!$response->successful()) {
                Log::warning('TelegramNotificationService: send non-200', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('TelegramNotificationService: send exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getBotToken(): ?string
    {
        return $this->getSetting('telegram_bot_token');
    }

    private function getChatId(): ?string
    {
        return $this->getSetting('telegram_chat_id');
    }

    private function getSetting(string $key): ?string
    {
        $value = Setting::where('group', 'telegram')->where('key', $key)->value('value');
        return is_string($value) ? $value : null;
    }

    private function entityIcon(?string $type): string
    {
        return match ($type) {
            'person' => '👤',
            'landmark' => '🏛️',
            'logo' => '🏢',
            'product' => '📦',
            default => '•',
        };
    }

    private function escapeMarkdown(string $s): string
    {
        // Minimal escape for Telegram Markdown (legacy) — guards against
        // user-supplied content (article titles, entity names) breaking
        // the formatting. Only _ * [ ` need escaping in legacy mode.
        return str_replace(['_', '*', '[', '`'], ['\\_', '\\*', '\\[', '\\`'], $s);
    }

    private function buildAdminUrl(ContentIdea $idea): string
    {
        return rtrim(config('app.url'), '/') . '/admin/content-engine?idea=' . $idea->id;
    }
}
