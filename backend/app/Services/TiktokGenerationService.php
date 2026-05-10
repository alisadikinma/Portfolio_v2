<?php

namespace App\Services;

use App\Enums\TiktokPostStatus;
use App\Models\TiktokPost;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Bridges Portfolio_v2 backend to the `social-short-form-writer` plugin's
 * `/tiktok-gen` skill (https://github.com/alisadikinma/social-short-form-writer).
 *
 * Plugin emits ONE JSON envelope to stdout matching `TiktokOutputEnvelopeSchema`:
 *   { status: 'complete'|'failed',
 *     title: string, caption: string, hashtags: string[],
 *     suggested_time_slot?: {...}, validation: {...} }
 *
 * Hard rules enforced by Zod:
 *   - hashtags 5-8 (TikTok 2026 search-index signal)
 *   - caption ≤2200 chars; first 100 chars search-index gated
 *   - title ≤100 chars (shorter than IG)
 *   - link in caption is OK (TikTok allows it)
 *   - NO music_suggestion field (Publer auto-handles trending music)
 *
 * FSM: PendingGeneration|Failed|Cancelled → Generating → AwaitingReview
 */
class TiktokGenerationService extends BaseSocialGenerationService
{
    protected string $skillName = 'tiktok-gen';
    protected string $refsConfigKey = 'social-cross-post.generation.refs_tiktok';

    public function __construct(
        private readonly PipelineGuard $guard,
    ) {
    }

    /**
     * @return array{success: bool, draft_id: int, status: string, error?: string|null}
     */
    public function generate(TiktokPost $draft): array
    {
        if ($draft->status === TiktokPostStatus::Generating->value) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => "Draft already in-flight (status={$draft->status})",
            ];
        }

        try {
            $this->guard->advance($draft, TiktokPostStatus::Generating, 'plugin_dispatch_start');
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => $e->getMessage(),
            ];
        }

        $input = $this->buildPluginInput($draft);
        if ($input === null) {
            $this->markFailed($draft, 'Cannot build plugin input — LinkedIn post or carousel slides missing');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => TiktokPostStatus::Failed->value,
                'error' => 'Source LinkedIn carousel missing or empty',
            ];
        }

        try {
            $result = $this->invokePlugin($input, $draft->id);
        } catch (\Throwable $e) {
            $errMsg = 'SSH dispatch threw: ' . $e->getMessage();
            $this->markFailed($draft, $errMsg);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => TiktokPostStatus::Failed->value,
                'error' => $errMsg,
            ];
        }

        if (!$result['success']) {
            $errMsg = $result['error'] ?? 'Plugin invocation failed';
            $this->markFailed($draft, $errMsg);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => TiktokPostStatus::Failed->value,
                'error' => $errMsg,
            ];
        }

        $parsed = $this->parseOrchestratorOutput($result['stdout']);
        if ($parsed === null) {
            $this->markFailed($draft, 'Could not parse /tiktok-gen JSON from stdout');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => TiktokPostStatus::Failed->value,
                'error' => 'Invalid JSON from plugin',
            ];
        }

        if (($parsed['status'] ?? null) === 'failed') {
            $errMsg = $parsed['error'] ?? 'Plugin reported failed without message';
            if (is_array($errMsg)) {
                $errMsg = json_encode($errMsg);
            }
            $this->markFailed($draft, "Plugin failed: {$errMsg}");
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => TiktokPostStatus::Failed->value,
                'error' => (string) $errMsg,
            ];
        }

        return $this->persistAndRoute($draft, $parsed);
    }

    public function buildPluginInput(TiktokPost $draft): ?array
    {
        if (!$draft->relationLoaded('linkedinPost')
            || !$draft->relationLoaded('post')
            || ($draft->post && !$draft->post->relationLoaded('translations'))) {
            $draft->loadMissing(['linkedinPost', 'post.translations']);
        }

        $post = $draft->post;
        $linkedinPost = $draft->linkedinPost;
        if ($post === null || $linkedinPost === null) {
            return null;
        }

        $slides = is_array($linkedinPost->carousel_slides ?? null)
            ? $linkedinPost->carousel_slides
            : [];
        if (empty($slides)) {
            return null;
        }

        // Prefer ID translation (plugin v0.3.0+ authors Bahasa Indonesia by default).
        // Fall back to EN for legacy posts authored before article-translate pipeline shipped.
        $translation = $post->translations->firstWhere('language', 'id')
            ?? $post->translations->firstWhere('language', 'en')
            ?? $post->translations->first();

        if ($translation === null) {
            return null;
        }

        $title = trim((string) $translation->title);
        $excerpt = trim((string) ($translation->excerpt ?? ''));
        $metaKeywords = trim((string) ($translation->meta_keywords ?? ''));

        if ($title === '') {
            return null;
        }

        // Use branded shortener with TikTok UTM attribution (May 10, 2026).
        // TikTok caption body carries the URL (no first-comment support on
        // TikTok via Publer API); shortener saves ~70-95 chars on long slugs.
        try {
            $blogUrl = app(\App\Services\ShortLinkService::class)
                ->forBlogPost($post, 'tiktok');
        } catch (\Throwable $e) {
            $appUrl = rtrim((string) config('app.url', 'https://alisadikinma.com'), '/');
            $blogUrl = $appUrl . '/blog/' . $post->slug;
        }

        return [
            'blog' => [
                'title' => $title,
                'content' => $this->stripHtmlToText((string) $translation->content),
                'excerpt' => $excerpt !== '' ? $excerpt : null,
                'meta_keywords' => $metaKeywords !== '' ? $metaKeywords : null,
                'slug' => $post->slug,
                // TikTok caption MAY include the blog URL (link-in-caption OK).
                // /instagram-gen schema rejects URLs but /tiktok-gen accepts them.
                'blog_url' => $blogUrl,
            ],
            'content_idea' => $this->buildContentIdeaPayload($post),
            'carousel_slides' => $this->normalizeSlides($slides),
            'format' => 'photo_carousel_9_16',
            'posting_time_options' => [],
        ];
    }

    private function buildContentIdeaPayload(\App\Models\Post $post): ?array
    {
        if (!$post->relationLoaded('contentIdea')) {
            $post->loadMissing('contentIdea');
        }
        $idea = $post->contentIdea;
        if ($idea === null) {
            return null;
        }
        return [
            'pillar' => $idea->pillar,
            'virality_score' => $idea->virality_score,
        ];
    }

    private function normalizeSlides(array $slides): array
    {
        $normalized = [];
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $normalized[] = [
                'slide_number' => (int) ($slide['slide_number'] ?? count($normalized) + 1),
                'layout_hint' => (string) ($slide['layout_hint'] ?? 'body'),
                'copy_id' => $slide['copy_id'] ?? null,
                'copy_en' => $slide['copy_en'] ?? null,
                'image_url' => (string) ($slide['image_url'] ?? ''),
            ];
        }
        return $normalized;
    }

    private function stripHtmlToText(string $html): string
    {
        $text = preg_replace('/<\/(p|div|h[1-6]|li|blockquote|br)\s*>/i', "\n\n", $html);
        $text = preg_replace('/<br\s*\/?>/i', "\n", (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);
        return trim((string) $text);
    }

    /**
     * @return array{success: bool, draft_id: int, status: string}
     */
    protected function persistAndRoute(TiktokPost $draft, array $parsed): array
    {
        $title = (string) ($parsed['title'] ?? '');
        $caption = (string) ($parsed['caption'] ?? '');
        $hashtags = is_array($parsed['hashtags'] ?? null) ? $parsed['hashtags'] : [];
        $validation = is_array($parsed['validation'] ?? null) ? $parsed['validation'] : [];
        $passed = (bool) ($validation['passed'] ?? false);

        // TikTok hashtag bounds 5-8 (vs IG 3-5).
        if ($title === '' || $caption === '' || count($hashtags) < 5 || count($hashtags) > 8) {
            $this->markFailed(
                $draft,
                'Plugin output failed shape gate: title=' . strlen($title)
                . ' caption=' . strlen($caption)
                . ' hashtags=' . count($hashtags)
            );
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => TiktokPostStatus::Failed->value,
            ];
        }

        // Persist branded short URL for parity with other platforms (TikTok
        // doesn't support Publer first-comment; URL lives in caption body via
        // plugin input. Column populated for visibility / admin UI parity).
        $linkComment = null;
        if ($draft->post && !empty($draft->post->slug)) {
            try {
                $shortUrl = app(\App\Services\ShortLinkService::class)
                    ->forBlogPost($draft->post, 'tiktok');
                $linkComment = "Full article: {$shortUrl}";
            } catch (\Throwable $e) {
                Log::warning('[TiktokGenerationService] short link generation failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $draft->update([
            'title' => $title,
            'caption' => $caption,
            'link_comment' => $linkComment,
            'hashtags' => array_values(array_map('strval', $hashtags)),
        ]);

        if (!$passed) {
            $failures = $validation['failures'] ?? [];
            $reason = is_array($failures) && !empty($failures)
                ? 'Validation failed: ' . implode('; ', array_map('strval', $failures))
                : 'Validation failed (no specifics)';
            $this->markFailed($draft, $reason);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => TiktokPostStatus::Failed->value,
            ];
        }

        try {
            $this->guard->advance($draft, TiktokPostStatus::AwaitingReview, 'generation_complete');
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            Log::error('[TiktokGenerationService] FSM transition Generating→AwaitingReview failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
            ];
        }

        // Phase G — Telegram alert (dormant by default).
        try {
            App::make(\App\Services\TelegramNotificationService::class)
                ->sendCrossPostAwaitingReview('tiktok', $draft->id, $title, $caption);
        } catch (\Throwable $e) {
            Log::warning('[TiktokGenerationService] Telegram alert failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->maybeCascadeToPublisher($draft, TiktokPostStatus::Publishing, TiktokPost::class, $this->guard);

        return [
            'success' => true,
            'draft_id' => $draft->id,
            'status' => $draft->fresh()->status ?? TiktokPostStatus::AwaitingReview->value,
        ];
    }

    protected function markFailed(TiktokPost $draft, string $reason): void
    {
        try {
            $this->guard->advance($draft, TiktokPostStatus::Failed, 'generation_failed', [
                'reason' => $reason,
            ]);
            $draft->update(['last_error' => $reason]);
        } catch (\Throwable $e) {
            Log::error('[TiktokGenerationService] markFailed transition itself failed', [
                'draft_id' => $draft->id,
                'reason' => $reason,
                'transition_error' => $e->getMessage(),
            ]);
        }

        // Phase G — Telegram alert (dormant by default).
        try {
            App::make(\App\Services\TelegramNotificationService::class)
                ->sendCrossPostGenerationFailed('tiktok', $draft->id, $reason);
        } catch (\Throwable $e) {
            Log::warning('[TiktokGenerationService] Telegram failed-alert errored', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
