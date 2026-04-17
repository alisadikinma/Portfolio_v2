<?php

namespace App\Services;

use App\Models\ContentIdea;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramNotifier
{
    private const API_URL_TEMPLATE = 'https://api.telegram.org/bot%s/sendMessage';

    public function notifyPublishSuccess(Post $post): void
    {
        $message = $this->buildPublishMessage($post);
        $this->send($message);
    }

    public function notifyFinalFailure(ContentIdea $idea): void
    {
        $message = $this->buildFailureMessage($idea);
        $this->send($message);
    }

    private function send(string $message): void
    {
        if (!config('services.telegram.enabled', true)) {
            return;
        }

        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (empty($token) || empty($chatId)) {
            Log::warning('[Telegram] bot_token or chat_id not configured — skipping notification');
            return;
        }

        try {
            $response = Http::timeout(10)
                ->asJson()
                ->post(sprintf(self::API_URL_TEMPLATE, $token), [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => false,
                ]);

            if (!$response->successful()) {
                Log::warning('[Telegram] sendMessage failed', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[Telegram] sendMessage exception: ' . $e->getMessage());
        }
    }

    private function buildPublishMessage(Post $post): string
    {
        $translation = $post->translations()->first();
        $title = $translation?->title ?? ('Post #' . $post->id);
        $summary = $translation?->excerpt
            ?? $translation?->meta_description
            ?? '';
        $summary = $this->escapeMarkdown(Str::limit(strip_tags((string) $summary), 300));
        $title = $this->escapeMarkdown((string) $title);

        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://alisadikinma.com'), '/');
        $link = $frontendUrl . '/blog/' . $post->slug;

        $lines = [
            '🚀 *New blog published*',
            '',
            '*' . $title . '*',
            '',
            '🔗 ' . $link,
        ];

        if ($summary !== '') {
            $lines[] = '';
            $lines[] = '📝 ' . $summary;
        }

        $idea = $post->source_idea_id ? ContentIdea::find($post->source_idea_id) : null;
        if ($idea) {
            $lines[] = '';
            $lines[] = sprintf(
                'Pillar: %s | Source: %s',
                $this->escapeMarkdown((string) ($idea->pillar ?? 'n/a')),
                $this->escapeMarkdown((string) ($idea->source ?? 'n/a'))
            );
        }

        return implode("\n", $lines);
    }

    private function buildFailureMessage(ContentIdea $idea): string
    {
        $title = $this->escapeMarkdown((string) $idea->title);
        $stage = $this->escapeMarkdown((string) ($idea->pipeline_failed_stage ?? 'unknown'));

        $lastError = '';
        $log = $idea->progress_log ?? [];
        if (!empty($log) && is_array($log)) {
            $lastEntry = end($log);
            if (is_array($lastEntry) && !empty($lastEntry['message'])) {
                $lastError = $this->escapeMarkdown(Str::limit((string) $lastEntry['message'], 200));
            }
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://alisadikinma.com'), '/');
        $reviewUrl = $frontendUrl . '/admin/content-engine';

        $lines = [
            '⚠️ *Content pipeline failed* — needs manual review',
            '',
            '*' . $title . '*',
            '',
            'Failed stage: ' . $stage,
            'Attempts: 3/3',
        ];

        if ($lastError !== '') {
            $lines[] = 'Error: ' . $lastError;
        }

        $lines[] = '';
        $lines[] = 'Review: ' . $reviewUrl;

        return implode("\n", $lines);
    }

    private function escapeMarkdown(string $text): string
    {
        // Telegram Markdown (v1) reserves _ * ` [ — escape minimally to keep rendering safe.
        return str_replace(
            ['_', '*', '`', '['],
            ['\\_', '\\*', '\\`', '\\['],
            $text
        );
    }
}
