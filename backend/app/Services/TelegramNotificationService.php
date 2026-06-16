<?php

namespace App\Services;

use App\Models\ContentIdea;
use App\Models\LinkedInPost;
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
     * Daily heartbeat after content:pull-trending-daily completes. Without this,
     * a silent cron failure (queue worker crash, schedule:run cron removed, VPS
     * reboot without systemd auto-start) only surfaces when the operator
     * notices no new draft ideas, which can take days. Toggle key:
     * telegram_notify_trending_pulled (default OFF — opt-in to avoid spamming
     * operators who don't care about the daily ack).
     */
    public function sendTrendingDailyHeartbeat(int $imported, int $skippedDup, int $skippedLow, int $errors = 0): bool
    {
        if (!$this->isEnabledFor('trending_pulled')) {
            return false;
        }

        $threshold = (int) config('content.trending.virality_threshold', 70);
        $emoji = $errors > 0 ? '⚠️' : ($imported > 0 ? '📥' : '🪶');

        $text = "{$emoji} *Trending pull* — Content Engine\n\n"
            . "Imported: *{$imported}*\n"
            . "Skipped (dup): {$skippedDup}\n"
            . "Skipped (virality < {$threshold}): {$skippedLow}\n"
            . ($errors > 0 ? "Errors: *{$errors}*\n" : '')
            . "\n"
            . "[Open Content Engine](" . rtrim((string) config('app.url'), '/') . "/admin/content-engine)";

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
     * Weekly freshness digest (GEO publish-and-forget fix — Neil Patel #1).
     * SYSTEM-LEVEL — not keyed to a ContentIdea. Sends ONE message listing the
     * stale posts so the operator reviews or refreshes them (never auto-regen).
     * Gated by telegram_notify_stale_content. No-op when disabled or empty.
     *
     * @param array<int,array{id:int,title:string,days:int,slug?:string}> $posts
     */
    public function sendStaleContentDigest(array $posts): bool
    {
        if (!$this->isEnabledFor('stale_content')) {
            return false;
        }
        if (empty($posts)) {
            return false;
        }

        $count = count($posts);
        $shown = array_slice($posts, 0, 15);

        $lines = [];
        $lines[] = '🕸️ *Stale content — time to refresh*';
        $lines[] = '';
        $lines[] = $count === 1
            ? '1 published post is past the freshness window:'
            : "{$count} published posts are past the freshness window:";
        $lines[] = '';
        foreach ($shown as $p) {
            $title = $this->escapeMarkdown($this->truncate((string) ($p['title'] ?? 'Untitled'), 80));
            $days = (int) ($p['days'] ?? 0);
            $lines[] = "• _{$title}_ — {$days}d old";
        }
        if ($count > count($shown)) {
            $lines[] = '…and ' . ($count - count($shown)) . ' more.';
        }
        $lines[] = '';
        $lines[] = 'Review or refresh in [admin]('
            . rtrim((string) config('app.url'), '/') . '/admin/posts?filter=stale).';

        return $this->send(implode("\n", $lines));
    }

    /**
     * Idea-level HOLD escalation when image generation stalls (GEO
     * image-completion gate). SYSTEM-LEVEL — fired once per stall from
     * ImageGenerationService::handleSegmentFailure when a segment exhausts its
     * retry budget, so the operator knows the idea is HELD at generating_images
     * and won't auto-publish with a broken image. Gated by
     * telegram_notify_image_stalled. No-op when disabled.
     *
     * @param \App\Models\ContentIdea $idea
     * @param array<int,array{index:int,type:?string,status:?string}> $failedSegments
     */
    public function sendImageGenerationStalled(\App\Models\ContentIdea $idea, array $failedSegments): bool
    {
        if (!$this->isEnabledFor('image_stalled')) {
            return false;
        }

        $count = count($failedSegments);
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/content-engine?idea=' . $idea->id;

        $lines = [];
        $lines[] = '🛑 *Image generation stalled — idea HELD*';
        $lines[] = '';
        $lines[] = 'Article: _' . $this->escapeMarkdown((string) ($idea->title ?? 'Untitled')) . '_';
        $lines[] = $count === 1
            ? '1 image segment exhausted its retries — the idea will NOT publish until it is resolved.'
            : "{$count} image segments exhausted their retries — the idea will NOT publish until they are resolved.";
        foreach (array_slice($failedSegments, 0, 8) as $seg) {
            $idx = $seg['index'] ?? '?';
            $type = $seg['type'] ?? 'segment';
            $lines[] = "• #{$idx} ({$type})";
        }
        $lines[] = '';
        $lines[] = 'Retry, skip, or upload manually in [admin](' . $adminUrl . ').';

        return $this->send(implode("\n", $lines));
    }

    /**
     * Celebratory alert when a LinkedIn draft has been successfully posted to
     * the LinkedIn feed. Includes the live LinkedIn URL so the operator can
     * jump straight to the post (or to the comment thread to reply early).
     *
     * Toggle key: telegram_notify_linkedin_published.
     */
    public function sendLinkedInPublishSuccess(LinkedInPost $draft): bool
    {
        if (!$this->isEnabledFor('linkedin_published')) {
            return false;
        }

        $title = $draft->post?->translations->where('language', 'id')->first()?->title
            ?? $draft->post?->translations->first()?->title
            ?? 'Untitled';

        $format = strtoupper((string) ($draft->format ?? 'text'));
        $url = $draft->linkedin_post_url ?? null;
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/sosmed-drafts/' . $draft->id;

        $lines = [];
        $lines[] = '✅ *Published to LinkedIn* — ' . $format;
        $lines[] = '';
        $lines[] = 'Post: _' . $this->escapeMarkdown($title) . '_';

        if ($url) {
            $lines[] = '';
            $lines[] = '🔗 [Open on LinkedIn](' . $url . ')';
        } else {
            $lines[] = '';
            $lines[] = '_(no LinkedIn URL captured — check admin)_';
        }

        $lines[] = '[Open in admin](' . $adminUrl . ')';

        $keyboard = null;
        if ($url) {
            $keyboard = [
                'inline_keyboard' => [[
                    ['text' => '🔗 Open on LinkedIn', 'url' => $url],
                    ['text' => '🛠 Admin', 'url' => $adminUrl],
                ]],
            ];
        }

        return $this->send(implode("\n", $lines), $keyboard);
    }

    /**
     * Phase F — LinkedIn auto-retry exhaustion alert. Fires from
     * `linkedin:retry-failed` cron when a draft hits auto_retry_count >= 2
     * and is still in FSM=Failed. Includes inline buttons for one-click
     * resolve from Telegram (handled by TelegramWebhookController).
     */
    public function sendLinkedInAutoRetryExhausted(LinkedInPost $draft, string $lastError): bool
    {
        if (!$this->isEnabledFor('linkedin_auto_retry_exhausted')) {
            return false;
        }

        $title = $draft->post?->translations->where('language', 'id')->first()?->title
            ?? $draft->post?->translations->first()?->title
            ?? "(post #{$draft->post_id})";

        $errorClass = $draft->last_classified_error_class ?? 'unknown';
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/sosmed-drafts/' . $draft->id;

        $lines = [];
        $lines[] = '⚠️ *LinkedIn auto-retry exhausted*';
        $lines[] = '';
        $lines[] = 'Draft #' . $draft->id . ' (' . strtoupper((string) $draft->format) . ') — _' . $this->escapeMarkdown($title) . '_';
        $lines[] = 'Error class: `' . $errorClass . '` after ' . $draft->auto_retry_count . ' retries';
        if (!empty($lastError)) {
            $lines[] = '';
            $lines[] = '```' . "\n" . $this->truncate($lastError, 300) . "\n" . '```';
        }

        $keyboard = $this->buildResolveInlineKeyboard('linkedin', $draft->id, $adminUrl);

        return $this->send(implode("\n", $lines), $keyboard);
    }

    /**
     * Phase F — Content Idea auto-retry exhaustion alert. Fires when
     * AutoPipelineOrchestrator's classifier-driven gate halts retries
     * (POLICY_*, PERMANENT, UNKNOWN) OR existing pipeline_attempts cap
     * is hit.
     */
    public function sendIdeaAutoRetryExhausted(\App\Models\ContentIdea $idea, string $lastError): bool
    {
        if (!$this->isEnabledFor('idea_auto_retry_exhausted')) {
            return false;
        }

        $errorClass = $idea->last_classified_error_class ?? 'unknown';
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/content-engine?idea=' . $idea->id;

        $lines = [];
        $lines[] = '⚠️ *Content Engine auto-retry halted*';
        $lines[] = '';
        $lines[] = 'Idea #' . $idea->id . ' — _' . $this->escapeMarkdown((string) $idea->title) . '_';
        $lines[] = 'Stage: `' . ($idea->pipeline_failed_stage ?? 'unknown') . '`';
        $lines[] = 'Class: `' . $errorClass . '` (attempts ' . ($idea->pipeline_attempts ?? 0) . ')';
        if (!empty($lastError)) {
            $lines[] = '';
            $lines[] = '```' . "\n" . $this->truncate($lastError, 300) . "\n" . '```';
        }

        $keyboard = $this->buildResolveInlineKeyboard('idea', $idea->id, $adminUrl);

        return $this->send(implode("\n", $lines), $keyboard);
    }

    /**
     * Phase F — Carousel slide tier-2 fallback failure alert. Fires after
     * the in-process generic-stock prompt also gets rejected. Operator
     * decision required: retry slide manually with bespoke prompt OR
     * skip the slide and republish carousel without it.
     */
    public function sendCarouselSlideTier2Failed(LinkedInPost $draft, int $slideIndex, string $error): bool
    {
        if (!$this->isEnabledFor('carousel_tier2_failed')) {
            return false;
        }

        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/sosmed-drafts/' . $draft->id;

        $lines = [];
        $lines[] = '🛑 *Carousel slide tier-2 fallback failed*';
        $lines[] = '';
        $lines[] = 'Draft #' . $draft->id . ' slide ' . ($slideIndex + 1);
        if (!empty($error)) {
            $lines[] = '';
            $lines[] = '```' . "\n" . $this->truncate($error, 300) . "\n" . '```';
        }
        $lines[] = '';
        $lines[] = 'Operator action required — generic-stock prompt also rejected.';

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '🛠 Open in admin', 'url' => $adminUrl],
            ]],
        ];

        return $this->send(implode("\n", $lines), $keyboard);
    }

    /**
     * Build the [Approve manual retry] [Cancel draft] [Open admin] inline
     * keyboard for retry-exhaustion notifications. callback_data carries
     * an HMAC so TelegramWebhookController can verify the click came from
     * a legitimately-issued button (not a forged one).
     */
    private function buildResolveInlineKeyboard(string $kind, int $id, string $adminUrl): array
    {
        $secret = (string) $this->getSetting('telegram_webhook_secret');

        return [
            'inline_keyboard' => [
                [
                    ['text' => '🔁 Retry', 'callback_data' => self::signCallback('retry', $kind, $id, $secret)],
                    ['text' => '✕ Cancel', 'callback_data' => self::signCallback('cancel', $kind, $id, $secret)],
                ],
                [
                    ['text' => '🛠 Open in admin', 'url' => $adminUrl],
                ],
            ],
        ];
    }

    /**
     * Sign callback_data with truncated HMAC so the webhook handler can
     * verify the click. Format: "<action>:<kind>:<id>:<hmac12>".
     * Telegram callback_data is hard-capped at 64 bytes, so we keep the
     * HMAC at 12 hex chars (96 bits — adequate against honest forgery,
     * not a security boundary by itself; the Telegram webhook secret
     * header is the primary auth gate).
     */
    public static function signCallback(string $action, string $kind, int $id, string $secret): string
    {
        $base = "{$action}:{$kind}:{$id}";
        if ($secret === '') {
            // Pre-seeder boot: no secret yet. Embed sentinel so verifyCallback
            // can return null cleanly instead of accepting unsigned data.
            return $base . ':NOSECRET';
        }
        $hmac = substr(hash_hmac('sha256', $base . ':' . $secret, $secret), 0, 12);
        return $base . ':' . $hmac;
    }

    /**
     * Verify a callback_data string emitted by signCallback. Returns
     * ['action' => ..., 'kind' => ..., 'id' => ...] on valid input or
     * null when format is wrong / HMAC mismatches / secret missing.
     * Constant-time comparison via hash_equals.
     */
    public static function verifyCallback(string $callbackData, string $secret): ?array
    {
        if ($secret === '') {
            return null;
        }
        $parts = explode(':', $callbackData);
        if (count($parts) !== 4) {
            return null;
        }
        [$action, $kind, $idStr, $hmac] = $parts;
        if (!ctype_digit($idStr)) {
            return null;
        }
        $expected = substr(hash_hmac('sha256', "{$action}:{$kind}:{$idStr}:" . $secret, $secret), 0, 12);
        if (!hash_equals($expected, $hmac)) {
            return null;
        }
        return [
            'action' => $action,
            'kind' => $kind,
            'id' => (int) $idStr,
        ];
    }

    private function truncate(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
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

    /**
     * IG repurpose (D9): prompt the operator to choose output mode via two
     * inline buttons. callback_data is HMAC-signed (kind='repurpose') so
     * TelegramWebhookController can verify the tap. This is an interactive
     * reply, not a per-type notification — gated only on the master toggle +
     * token/chat (no telegram_notify_* key).
     */
    public function sendRepurposeModePrompt(\App\Models\RepurposeJob $job): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        $secret = (string) $this->getSetting('telegram_webhook_secret');
        $url = $this->escapeMarkdown($this->truncate((string) $job->source_url, 80));
        $angleLine = $job->angle
            ? "\nAngle: _" . $this->escapeMarkdown($this->truncate((string) $job->angle, 120)) . '_'
            : '';

        $text = "🔗 *IG repurpose* — pilih output:\n{$url}{$angleLine}";

        $secondRow = [
            // video_rebrand (June 13): re-skins a VIDEO carousel into Ali's
            // brand chrome — ships composited 4:5 MP4s for manual download.
            ['text' => '🎬 Video rebrand', 'callback_data' => self::signCallback('video_rebrand', 'repurpose', $job->id, $secret)],
        ];
        // video_full (June 16): full talking-head regenerate (Ali face/voice/ID),
        // rendered by the MacBook-local worker. Only shown when explicitly enabled.
        if ($this->getSetting('telegram_video_full_enabled') === 'true') {
            $secondRow[] = ['text' => '🎥 Video 60s', 'callback_data' => self::signCallback('video_full', 'repurpose', $job->id, $secret)];
        }

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '📝 Blog + Carousel', 'callback_data' => self::signCallback('blog', 'repurpose', $job->id, $secret)],
                    ['text' => '🎠 Carousel saja', 'callback_data' => self::signCallback('carousel', 'repurpose', $job->id, $secret)],
                ],
                $secondRow,
            ],
        ];

        return $this->send($text, $replyMarkup);
    }

    /**
     * IG repurpose: notify the operator that a job failed at some pipeline step
     * (capture/extract/research/rewrite/finalize). Plain status reply — gated
     * only on the master toggle + token/chat (no per-type telegram_notify_* key,
     * since a failure the operator triggered should always surface). Used by the
     * pipeline jobs' failJob() paths so a stuck job is never silent.
     */
    public function sendRepurposeFailed(\App\Models\RepurposeJob $job, string $reason): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        $url = $this->escapeMarkdown($this->truncate((string) $job->source_url, 80));
        $reasonLine = $this->escapeMarkdown($this->truncate($reason, 200));

        $text = "⚠️ *IG repurpose gagal* — {$this->repurposeHeader($job)}\n{$url}\n\nAlasan: {$reasonLine}\n\n"
            . 'Cek post-nya public/bisa diakses, atau paste ulang URL untuk retry.';

        return $this->send($text);
    }

    /**
     * video_rebrand A5: a hook/CTA clip exhausted its 3 auto-retries and the job
     * is now Failed — alert the operator to take action, with a one-tap inline
     * Retry button (HMAC-signed, kind='repurpose' → RepurposeRetryService) plus an
     * admin deep-link. Master-toggle gated like the sibling repurpose notifications
     * (an operator-triggered job's terminal failure should always surface).
     */
    public function sendRepurposeAssetsFailed(\App\Models\RepurposeJob $job, string $lastError): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        $secret = (string) $this->getSetting('telegram_webhook_secret');
        $adminUrl = rtrim((string) config('app.url'), '/').'/admin/repurpose/'.$job->id;
        $reasonLine = $this->escapeMarkdown($this->truncate($lastError !== '' ? $lastError : 'render failed after retries', 200));

        $lines = [];
        $lines[] = '🎬 '.$this->repurposeHeader($job).' — *klip gagal*';
        $lines[] = '';
        $lines[] = 'Hook/CTA gagal di-generate setelah 3x retry otomatis. Perlu action.';
        $lines[] = 'Error terakhir: '.$reasonLine;

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '🔁 Retry', 'callback_data' => self::signCallback('retry', 'repurpose', $job->id, $secret)],
                ['text' => '🛠 Buka admin', 'url' => $adminUrl],
            ]],
        ];

        return $this->send(implode("\n", $lines), $keyboard);
    }

    /**
     * IG repurpose: notify the operator that a draft is ready (finalize done).
     * blog mode → Content Engine link; carousel mode → /admin/sosmed-drafts link.
     * Plain status reply (master-toggle gated).
     */
    public function sendRepurposeDrafted(\App\Models\RepurposeJob $job, ?int $linkedinDraftId, int $correctedClaims): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        $header = $this->repurposeHeader($job);
        if ($job->mode === 'blog') {
            // Blog mode now seeds a DRAFT idea (no pre-written article) — the
            // operator runs the proper Content Engine pipeline for quality. The
            // claim-correction count is N/A here (Content Engine re-verifies).
            $text = "📝 *Draft blog dari IG siap* — {$header}\n"
                . "Materi sumber sudah disiapkan jadi brief.\n\n"
                . 'Buka Content Engine → klik *Start Research* untuk tulis artikel '
                . 'berkualitas (5-gate) → publish → carousel + cross-post otomatis: /admin/content-engine';
        } else {
            $claimLine = $correctedClaims > 0
                ? "{$correctedClaims} klaim dikoreksi + sumber dilampirkan."
                : 'Klaim diverifikasi, sumber dilampirkan.';
            $link = $linkedinDraftId ? "/admin/sosmed-drafts/{$linkedinDraftId}" : '/admin/social-studio';
            $text = "🎠 *Carousel draft siap* — {$header}\n{$claimLine}\n\nReview → {$link}";
        }

        return $this->send($text);
    }

    /**
     * IG repurpose: best-effort live progress bubble — one NEW chat message per
     * pipeline step (vs the transient answerCallbackQuery toast). Master-toggle
     * gated like the sibling repurpose notifications. Callers wrap this in
     * try/catch so a notify failure never blocks/reverses the pipeline FSM.
     */
    public function sendRepurposeProgress(\App\Models\RepurposeJob $job, string $text): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        // A6: prepend "job #id · topic" so the operator knows WHICH job is running.
        return $this->send($this->repurposeHeader($job)."\n".$text);
    }

    /**
     * A6: a consistent "job #id · _topic_" header line prepended to every repurpose
     * notification so the operator can tell which job/topic is being processed.
     */
    public function repurposeHeader(\App\Models\RepurposeJob $job): string
    {
        return 'job #'.$job->id.' · _'.$this->escapeMarkdown($this->truncate($job->displayTopic(), 80)).'_';
    }

    /**
     * Ask the operator when to publish a ready draft (Phase E/F of the Telegram
     * scheduling conversation). Renders up to 3 weekday/holiday/occupancy-filtered
     * slot buttons (callback_data = "slot{i}:schedule:{id}:{hmac}", index-encoded
     * to fit Telegram's 64-byte callback limit) + a free-text-override hint.
     *
     * Gated by the master telegram_enabled toggle (token + chat_id required). The
     * caller (linkedin:prompt-schedule) only consumes the one-shot on a true return.
     *
     * @param \Carbon\Carbon[] $candidateSlots ascending free slots (WIB)
     */
    public function sendSchedulePrompt(\App\Models\LinkedInPost $draft, array $candidateSlots): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        $secret = (string) $this->getSetting('telegram_webhook_secret');
        $title = $this->resolveDraftTitle($draft);

        $lines = [
            '🗓️ *Siap dijadwalkan* — ' . $this->escapeMarkdown($this->truncate($title, 90)),
            'Kapan mau posting? Pilih slot di bawah, atau ketik sendiri (mis. `17 Jun 18:00`).',
        ];

        $rows = [];
        foreach (array_values($candidateSlots) as $i => $slot) {
            $label = $slot->locale('id')->isoFormat('ddd, D MMM HH:mm') . ' WIB';
            $rows[] = [[
                'text' => $label,
                'callback_data' => self::signCallback("slot{$i}", 'schedule', $draft->id, $secret),
            ]];
        }

        return $this->send(implode("\n", $lines), ['inline_keyboard' => $rows]);
    }

    /**
     * Best-effort display title for a LinkedIn draft's source post (EN
     * translation → first translation → slug → "Draft #id").
     */
    private function resolveDraftTitle(\App\Models\LinkedInPost $draft): string
    {
        $post = $draft->post;
        if ($post === null) {
            return 'Draft #' . $draft->id;
        }

        $translations = $post->translations ?? collect();
        $t = $translations->firstWhere('language', 'en') ?? $translations->first();

        return (string) ($t->title ?? $post->slug ?? ('Draft #' . $draft->id));
    }

    /**
     * The operator's typed slot collides (±30 min) with an existing scheduled
     * draft — ask for a Ya-tetap / Pilih-lain confirmation (Phase F).
     *
     * @param array{id:int,post_title:string,scheduled_at:string,minutes_apart:int} $conflict
     */
    public function sendScheduleConflict(\App\Models\LinkedInPost $draft, \Carbon\Carbon $proposed, array $conflict): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        $secret = (string) $this->getSetting('telegram_webhook_secret');
        $when = $proposed->locale('id')->isoFormat('ddd, D MMM HH:mm') . ' WIB';
        $other = $this->escapeMarkdown($this->truncate((string) ($conflict['post_title'] ?? 'draft lain'), 60));

        $lines = [
            '⚠️ Slot *' . $when . '* sudah ada draft #' . (int) ($conflict['id'] ?? 0) . " (\"{$other}\").",
            'Tetap jadwalkan di situ?',
        ];

        $replyMarkup = [
            'inline_keyboard' => [[
                ['text' => '✅ Ya, tetap', 'callback_data' => self::signCallback('confirm', 'schedule', $draft->id, $secret)],
                ['text' => '↩️ Pilih lain', 'callback_data' => self::signCallback('reject', 'schedule', $draft->id, $secret)],
            ]],
        ];

        return $this->send(implode("\n", $lines), $replyMarkup);
    }

    /** Confirm a draft has been scheduled (Phase F). */
    public function sendScheduleConfirmed(\App\Models\LinkedInPost $draft, \Carbon\Carbon $slot): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        $when = $slot->locale('id')->isoFormat('ddd, D MMM HH:mm') . ' WIB';
        $title = $this->escapeMarkdown($this->truncate($this->resolveDraftTitle($draft), 70));

        return $this->send("✅ *Dijadwalkan* {$when}\n{$title}");
    }

    /**
     * Generic gated send for the scheduling conversation (parse-help, invalid
     * datetime re-prompts). Goes to the configured operator chat (Phase F).
     */
    public function sendScheduleNotice(string $text): bool
    {
        if ($this->getSetting('telegram_enabled') !== 'true') {
            return false;
        }
        if (empty($this->getBotToken()) || empty($this->getChatId())) {
            return false;
        }

        return $this->send($text);
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

    /* ================================================================
     * Phase G — Cross-post pipeline alerts (FB + IG + TT).
     *
     * Toggle keys (default false — dormant until operator opts in via
     * admin Telegram settings panel):
     *   telegram_notify_crosspost_fanout
     *   telegram_notify_crosspost_generation_failed
     *   telegram_notify_crosspost_awaiting_review
     *   telegram_notify_crosspost_publish_success
     *   telegram_notify_crosspost_publish_failed
     *
     * Each method takes a generic Eloquent draft (FacebookPost,
     * InstagramPost, or TiktokPost) and renders a platform-aware Telegram
     * card with admin deep-link.
     * ================================================================ */

    /**
     * Fires from `social-cross-post:scan` when the scanner creates 1+
     * cross-post rows for a LinkedIn draft. Single message lists all
     * platforms fanned out.
     *
     * @param  array<int,string>  $platforms  e.g. ['facebook', 'instagram', 'tiktok']
     */
    public function sendCrossPostFanout(int $linkedinPostId, int $postId, array $platforms): bool
    {
        if (!$this->isEnabledFor('crosspost_fanout')) {
            return false;
        }
        if (empty($platforms)) {
            return false;
        }

        $platformList = implode(', ', array_map(fn ($p) => '`' . strtoupper($p) . '`', $platforms));
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/sosmed-drafts/' . $linkedinPostId;

        $lines = [];
        $lines[] = '🔀 *Cross-post fanout*';
        $lines[] = '';
        $lines[] = 'Source: LinkedIn draft #' . $linkedinPostId . ' (post #' . $postId . ')';
        $lines[] = 'Fanned out to: ' . $platformList;
        $lines[] = '';
        $lines[] = '[Open source in admin](' . $adminUrl . ')';

        return $this->send(implode("\n", $lines));
    }

    /**
     * Fires from {Instagram,Tiktok,Facebook}GenerationService::markFailed
     * after the plugin SSH dispatch / parser / validation fails.
     */
    public function sendCrossPostGenerationFailed(string $platform, int $draftId, string $error): bool
    {
        if (!$this->isEnabledFor('crosspost_generation_failed')) {
            return false;
        }

        $platformLabel = strtoupper($platform);
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/' . strtolower($platform) . '-drafts/' . $draftId;

        $lines = [];
        $lines[] = '🛑 *' . $platformLabel . ' generation failed*';
        $lines[] = '';
        $lines[] = 'Draft #' . $draftId;
        $lines[] = '';
        $lines[] = '```' . "\n" . $this->truncate($error, 300) . "\n" . '```';
        $lines[] = '';
        $lines[] = '[Open in admin](' . $adminUrl . ')';

        return $this->send(implode("\n", $lines));
    }

    /**
     * Fires when generation completes and a draft hits AwaitingReview —
     * signals operator there's something to review + approve in admin UI.
     * Optional caption/title preview keeps the alert actionable.
     */
    public function sendCrossPostAwaitingReview(string $platform, int $draftId, ?string $title, ?string $caption): bool
    {
        if (!$this->isEnabledFor('crosspost_awaiting_review')) {
            return false;
        }

        $platformLabel = strtoupper($platform);
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/' . strtolower($platform) . '-drafts/' . $draftId;

        $lines = [];
        $lines[] = '👀 *' . $platformLabel . ' draft ready for review*';
        $lines[] = '';
        $lines[] = 'Draft #' . $draftId;
        if (!empty($title)) {
            $lines[] = '_' . $this->escapeMarkdown($this->truncate($title, 100)) . '_';
        }
        if (!empty($caption)) {
            $lines[] = '';
            $lines[] = '> ' . $this->escapeMarkdown($this->truncate($caption, 200));
        }
        $lines[] = '';
        $lines[] = '[Review in admin](' . $adminUrl . ')';

        return $this->send(implode("\n", $lines));
    }

    /**
     * Fires from PublishViaPubler when the publish job completes
     * successfully (Phase H+). Includes the public Publer-confirmed
     * external_url when available.
     */
    public function sendCrossPostPublishSuccess(string $platform, int $draftId, ?string $externalUrl): bool
    {
        if (!$this->isEnabledFor('crosspost_publish_success')) {
            return false;
        }

        $platformLabel = strtoupper($platform);
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/' . strtolower($platform) . '-drafts/' . $draftId;

        $lines = [];
        $lines[] = '✅ *Published to ' . $platformLabel . '*';
        $lines[] = '';
        $lines[] = 'Draft #' . $draftId;
        if (!empty($externalUrl)) {
            $lines[] = '🔗 [Open on ' . $platformLabel . '](' . $externalUrl . ')';
        }
        $lines[] = '[Open in admin](' . $adminUrl . ')';

        return $this->send(implode("\n", $lines));
    }

    /* ================================================================
     * Phase I — GeminiGen circuit breaker state-transition alerts.
     *
     * Toggle keys (gated by isEnabledFor()):
     *   telegram_notify_geminigen_circuit_open   (default 'true')
     *   telegram_notify_geminigen_circuit_close  (default 'true')
     *
     * These are SYSTEM-LEVEL alerts (no ContentIdea / LinkedInPost
     * context) — fired directly from GeminiGenCircuitBreaker::setOpen
     * and ::setClosed. Per-segment Telegram alerts (segment_failed,
     * cover_critical) are suppressed in ImageGenerationService when
     * the breaker state != closed, so the operator gets exactly ONE
     * outage alert instead of N segment spam messages.
     * ================================================================ */

    /**
     * Fires when the circuit transitions closed → open (or external
     * forceOpen). Includes opened_at, next probe time, and a short
     * reason label for telemetry.
     */
    public function sendGeminigenCircuitOpen(?\Carbon\Carbon $openedAt = null, ?\Carbon\Carbon $nextProbeAt = null, ?string $reason = null): bool
    {
        if (!$this->isEnabledFor('geminigen_circuit_open')) {
            return false;
        }

        $openedAtStr = $openedAt
            ? $openedAt->copy()->setTimezone('Asia/Jakarta')->format('H:i')
            : 'now';
        $probeStr = $nextProbeAt
            ? $nextProbeAt->copy()->setTimezone('Asia/Jakarta')->format('H:i')
            : 'in 5 min';
        $reasonStr = $reason !== null && $reason !== ''
            ? $this->escapeMarkdown($reason)
            : 'failure threshold reached';

        $lines = [];
        $lines[] = '🔴 *GeminiGen outage detected*';
        $lines[] = '';
        $lines[] = 'Opened at: `' . $openedAtStr . ' WIB`';
        $lines[] = 'Reason: `' . $reasonStr . '`';
        $lines[] = 'Next probe: `' . $probeStr . ' WIB`';
        $lines[] = '';
        $lines[] = 'Pausing image dispatches. Per-segment alerts suppressed while circuit is open.';

        return $this->send(implode("\n", $lines));
    }

    /**
     * Fires when the circuit transitions half_open → closed (recovery
     * confirmed by a successful real dispatch). Reports outage duration
     * so operator can correlate with downstream queue backlog.
     */
    public function sendGeminigenCircuitClose(?\Carbon\Carbon $openedAt = null): bool
    {
        if (!$this->isEnabledFor('geminigen_circuit_close')) {
            return false;
        }

        $duration = $openedAt
            ? $openedAt->diffForHumans(\Carbon\Carbon::now(), ['parts' => 2, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
            : 'unknown duration';

        $lines = [];
        $lines[] = '🟢 *GeminiGen recovered*';
        $lines[] = '';
        $lines[] = 'Outage duration: `' . $this->escapeMarkdown($duration) . '`';
        $lines[] = '';
        $lines[] = 'Resuming image dispatches. Stuck retries will pick up on the next worker tick.';

        return $this->send(implode("\n", $lines));
    }

    /**
     * Fires from PublishViaPubler when the publish job fails (Phase H+).
     */
    public function sendCrossPostPublishFailed(string $platform, int $draftId, string $error): bool
    {
        if (!$this->isEnabledFor('crosspost_publish_failed')) {
            return false;
        }

        $platformLabel = strtoupper($platform);
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/' . strtolower($platform) . '-drafts/' . $draftId;

        $lines = [];
        $lines[] = '❌ *' . $platformLabel . ' publish failed*';
        $lines[] = '';
        $lines[] = 'Draft #' . $draftId;
        $lines[] = '';
        $lines[] = '```' . "\n" . $this->truncate($error, 300) . "\n" . '```';
        $lines[] = '';
        $lines[] = '[Open in admin](' . $adminUrl . ')';

        return $this->send(implode("\n", $lines));
    }
}
