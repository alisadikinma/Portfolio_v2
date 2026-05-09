<?php

namespace App\Services;

use App\Enums\FacebookPostStatus;
use App\Enums\InstagramPostStatus;
use App\Models\FacebookPost;
use App\Models\InstagramPost;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Caption authoring for Facebook Page cross-post drafts.
 *
 * Format-aware branching (per plan decision — saves ~1 day vs `/facebook-gen`
 * skill):
 *
 *   - format='text'     → read $draft->linkedinPost->content directly,
 *                          truncate to 1900 chars if >2000 (defense — LinkedIn
 *                          sweet spot is 1100-1300 so ≥99% of input fits),
 *                          set link_url to the blog URL (FB algorithm REWARDS
 *                          link-in-body — auto-renders preview card; opposite
 *                          of LinkedIn's 60% reach penalty). NO plugin call.
 *
 *   - format='carousel' → SSH-invoke `/instagram-gen` (REUSED — IG carousel
 *                          and FB carousel are both 4:5 photo with very
 *                          similar caption rules). Maps output to FB shape.
 *                          NO link_url (carousel posts don't carry a link
 *                          field; CTA goes in the slide images).
 *
 * Trade-off documented in CLAUDE.md and the plan: FB output is "second-class
 * citizen" vs native authoring (50-70% engagement potential), but the cost
 * savings justify it given FB Page is a secondary channel for the brand.
 *
 * FSM (FacebookPostStatus mirrors InstagramPostStatus):
 *   PendingGeneration|Failed|Cancelled → Generating → AwaitingReview
 *                                                 → Failed (any error)
 */
class FacebookGenerationService extends BaseSocialGenerationService
{
    // Used only on the carousel path (delegates to /instagram-gen).
    protected string $skillName = 'instagram-gen';
    protected string $refsConfigKey = 'social-cross-post.generation.refs_instagram';

    public function __construct(
        private readonly PipelineGuard $guard,
    ) {
    }

    /**
     * @return array{success: bool, draft_id: int, status: string, error?: string|null}
     */
    public function generate(FacebookPost $draft): array
    {
        if ($draft->status === FacebookPostStatus::Generating->value) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => "Draft already in-flight (status={$draft->status})",
            ];
        }

        try {
            $this->guard->advance($draft, FacebookPostStatus::Generating, 'plugin_dispatch_start');
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => $e->getMessage(),
            ];
        }

        $format = (string) $draft->format;
        if ($format === 'text') {
            return $this->generateText($draft);
        }
        if ($format === 'carousel') {
            return $this->generateCarousel($draft);
        }

        $this->markFailed($draft, "Unknown format='{$format}' — expected 'text' or 'carousel'");
        return [
            'success' => false,
            'draft_id' => $draft->id,
            'status' => FacebookPostStatus::Failed->value,
            'error' => "Invalid format: {$format}",
        ];
    }

    /**
     * Text-format path — reuses LinkedIn content directly. NO plugin call.
     */
    private function generateText(FacebookPost $draft): array
    {
        if (!$draft->relationLoaded('linkedinPost')
            || !$draft->relationLoaded('post')) {
            $draft->loadMissing(['linkedinPost', 'post']);
        }

        $linkedinPost = $draft->linkedinPost;
        $post = $draft->post;
        if ($linkedinPost === null || $post === null) {
            $this->markFailed($draft, 'Source LinkedIn post or blog post missing');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => FacebookPostStatus::Failed->value,
                'error' => 'Source missing',
            ];
        }

        $sourceContent = trim((string) $linkedinPost->content);
        if ($sourceContent === '') {
            $this->markFailed($draft, 'Source LinkedIn content is empty');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => FacebookPostStatus::Failed->value,
                'error' => 'Empty source',
            ];
        }

        $caption = $this->fitForFacebookText($sourceContent);

        $appUrl = rtrim((string) config('app.url', 'https://alisadikinma.com'), '/');
        $linkUrl = $appUrl . '/blog/' . $post->slug;

        $hashtags = is_array($linkedinPost->hashtags ?? null)
            ? array_slice(array_values(array_map('strval', $linkedinPost->hashtags)), 0, 3)
            : [];

        $title = trim((string) $linkedinPost->title);
        if ($title === '') {
            $title = $this->extractFirstLine($caption);
        }

        $draft->update([
            'title' => mb_substr($title, 0, 200),
            'caption' => $caption,
            'hashtags' => $hashtags,
            'link_url' => $linkUrl,
        ]);

        try {
            $this->guard->advance(
                $draft,
                FacebookPostStatus::AwaitingReview,
                'text_format_reuse_complete',
                ['source' => 'linkedin_post.content']
            );
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            Log::error('[FacebookGenerationService] FSM transition failed (text path)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
            ];
        }

        $this->fireAwaitingReviewAlert($draft, $title, $caption);

        $this->maybeCascadeToPublisher($draft, FacebookPostStatus::Publishing, FacebookPost::class, $this->guard);

        return [
            'success' => true,
            'draft_id' => $draft->id,
            'status' => $draft->fresh()->status ?? FacebookPostStatus::AwaitingReview->value,
        ];
    }

    /**
     * Carousel-format path — SSH-invokes /instagram-gen and maps output.
     * Does NOT set link_url (carousel posts don't carry a link field).
     */
    private function generateCarousel(FacebookPost $draft): array
    {
        $input = $this->buildPluginInput($draft);
        if ($input === null) {
            $this->markFailed($draft, 'Cannot build plugin input — LinkedIn carousel slides missing');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => FacebookPostStatus::Failed->value,
                'error' => 'Source carousel missing',
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
                'status' => FacebookPostStatus::Failed->value,
                'error' => $errMsg,
            ];
        }

        if (!$result['success']) {
            $errMsg = $result['error'] ?? 'Plugin invocation failed';
            $this->markFailed($draft, $errMsg);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => FacebookPostStatus::Failed->value,
                'error' => $errMsg,
            ];
        }

        $parsed = $this->parseOrchestratorOutput($result['stdout']);
        if ($parsed === null) {
            $this->markFailed($draft, 'Could not parse /instagram-gen JSON from stdout');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => FacebookPostStatus::Failed->value,
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
                'status' => FacebookPostStatus::Failed->value,
                'error' => (string) $errMsg,
            ];
        }

        return $this->persistCarousel($draft, $parsed);
    }

    public function buildPluginInput(FacebookPost $draft): ?array
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

        $translation = $post->translations->firstWhere('language', 'en')
            ?? $post->translations->firstWhere('language', 'id')
            ?? $post->translations->first();

        if ($translation === null) {
            return null;
        }

        $title = trim((string) $translation->title);
        if ($title === '') {
            return null;
        }

        return [
            'blog' => [
                'title' => $title,
                'content' => $this->stripHtmlToText((string) $translation->content),
                'excerpt' => trim((string) ($translation->excerpt ?? '')) ?: null,
                'meta_keywords' => trim((string) ($translation->meta_keywords ?? '')) ?: null,
                'slug' => $post->slug,
            ],
            'content_idea' => $this->buildContentIdeaPayload($post),
            'carousel_slides' => $this->normalizeSlides($slides),
            'format' => 'photo_carousel',
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
     * Trim LinkedIn content for FB Page text format. FB Page hard limit is
     * ~63k chars but optimal engagement length is 40-80 chars (per CLAUDE.md
     * research). We don't aggressively rewrite (operator can edit in admin
     * UI); we just defend against pathological long content by trimming to
     * 1900 chars max with ellipsis. LinkedIn sweet spot is 1100-1300 so this
     * fires for <1% of content.
     */
    private function fitForFacebookText(string $source): string
    {
        if (mb_strlen($source) <= 2000) {
            return $source;
        }
        return mb_substr($source, 0, 1900) . '…';
    }

    private function extractFirstLine(string $text): string
    {
        $line = strtok($text, "\n");
        if ($line === false) {
            return '';
        }
        return mb_substr(trim($line), 0, 200);
    }

    /**
     * Persist /instagram-gen output mapped to FB carousel shape.
     * Same hashtag bound (3-5) as IG since the plugin authored under IG rules.
     */
    private function persistCarousel(FacebookPost $draft, array $parsed): array
    {
        $title = (string) ($parsed['title'] ?? '');
        $caption = (string) ($parsed['caption'] ?? '');
        $hashtags = is_array($parsed['hashtags'] ?? null) ? $parsed['hashtags'] : [];
        $validation = is_array($parsed['validation'] ?? null) ? $parsed['validation'] : [];
        $passed = (bool) ($validation['passed'] ?? false);

        if ($title === '' || $caption === '' || count($hashtags) < 3 || count($hashtags) > 5) {
            $this->markFailed(
                $draft,
                'Plugin output failed shape gate: title=' . strlen($title)
                . ' caption=' . strlen($caption)
                . ' hashtags=' . count($hashtags)
            );
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => FacebookPostStatus::Failed->value,
            ];
        }

        $draft->update([
            'title' => $title,
            'caption' => $caption,
            'hashtags' => array_values(array_map('strval', $hashtags)),
            'link_url' => null, // carousel posts don't carry a link field
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
                'status' => FacebookPostStatus::Failed->value,
            ];
        }

        try {
            $this->guard->advance(
                $draft,
                FacebookPostStatus::AwaitingReview,
                'carousel_format_complete',
                ['source' => 'instagram_gen_reuse']
            );
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            Log::error('[FacebookGenerationService] FSM transition failed (carousel path)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
            ];
        }

        $this->fireAwaitingReviewAlert($draft, $title, $caption);

        $this->maybeCascadeToPublisher($draft, FacebookPostStatus::Publishing, FacebookPost::class, $this->guard);

        return [
            'success' => true,
            'draft_id' => $draft->id,
            'status' => $draft->fresh()->status ?? FacebookPostStatus::AwaitingReview->value,
        ];
    }

    private function fireAwaitingReviewAlert(FacebookPost $draft, string $title, string $caption): void
    {
        try {
            App::make(\App\Services\TelegramNotificationService::class)
                ->sendCrossPostAwaitingReview('facebook', $draft->id, $title, $caption);
        } catch (\Throwable $e) {
            Log::warning('[FacebookGenerationService] Telegram alert failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function markFailed(FacebookPost $draft, string $reason): void
    {
        try {
            $this->guard->advance($draft, FacebookPostStatus::Failed, 'generation_failed', [
                'reason' => $reason,
            ]);
            $draft->update(['last_error' => $reason]);
        } catch (\Throwable $e) {
            Log::error('[FacebookGenerationService] markFailed transition itself failed', [
                'draft_id' => $draft->id,
                'reason' => $reason,
                'transition_error' => $e->getMessage(),
            ]);
        }

        // Phase G — Telegram alert (dormant by default).
        try {
            App::make(\App\Services\TelegramNotificationService::class)
                ->sendCrossPostGenerationFailed('facebook', $draft->id, $reason);
        } catch (\Throwable $e) {
            Log::warning('[FacebookGenerationService] Telegram failed-alert errored', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
