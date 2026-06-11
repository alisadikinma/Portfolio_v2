<?php

namespace App\Services;

use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

/**
 * Bridges Portfolio_v2 backend to the `social-short-form-writer` plugin's
 * `/instagram-gen` skill (https://github.com/alisadikinma/social-short-form-writer).
 *
 * Plugin emits ONE JSON envelope to stdout matching `InstagramOutputEnvelopeSchema`:
 *   { status: 'complete'|'failed',
 *     title: string, caption: string, hashtags: string[],
 *     suggested_time_slot?: {...}, validation: {...} }
 *
 * Per the plugin design, hard rules enforced by Zod:
 *   - hashtags 3-5 (Dec 2025 IG hardcap)
 *   - caption ≤2200 chars, NO URL in caption (IG canonical)
 *   - title ≤125 chars (first-line hook)
 *
 * Execution is SYNCHRONOUS from this service's perspective — the plugin runs
 * ~30-60s on the VPS. Always invoke from a queued `GenerateInstagramPost` job.
 *
 * FSM: PendingGeneration|Failed|Cancelled → Generating → AwaitingReview
 *      (validation.passed=true) | Failed (validation.passed=false or any error)
 */
class InstagramGenerationService extends BaseSocialGenerationService
{
    protected string $skillName = 'instagram-gen';
    protected string $refsConfigKey = 'social-cross-post.generation.refs_instagram';

    public function __construct(
        private readonly PipelineGuard $guard,
    ) {
    }

    /**
     * Generate caption + hashtags + suggested time slot for an InstagramPost
     * draft by SSH-invoking the plugin's /instagram-gen skill on the VPS.
     *
     * @return array{success: bool, draft_id: int, status: string, error?: string|null}
     */
    public function generate(InstagramPost $draft): array
    {
        if ($draft->status === InstagramPostStatus::Generating->value) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => "Draft already in-flight (status={$draft->status})",
            ];
        }

        try {
            $this->guard->advance($draft, InstagramPostStatus::Generating, 'plugin_dispatch_start');
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
                'status' => InstagramPostStatus::Failed->value,
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
                'status' => InstagramPostStatus::Failed->value,
                'error' => $errMsg,
            ];
        }

        if (!$result['success']) {
            $errMsg = $result['error'] ?? 'Plugin invocation failed';
            $this->markFailed($draft, $errMsg);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => InstagramPostStatus::Failed->value,
                'error' => $errMsg,
            ];
        }

        $parsed = $this->parseOrchestratorOutput($result['stdout']);
        if ($parsed === null) {
            $this->markFailed($draft, 'Could not parse /instagram-gen JSON from stdout');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => InstagramPostStatus::Failed->value,
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
                'status' => InstagramPostStatus::Failed->value,
                'error' => (string) $errMsg,
            ];
        }

        return $this->persistAndRoute($draft, $parsed);
    }

    /**
     * Build the input payload shipped to the plugin via SSH.
     *
     * The InstagramPost is always sourced from a LinkedIn carousel
     * (format=carousel) — IG only does carousel format in this pipeline,
     * never single-photo. We pass through the rendered slide URLs +
     * blog metadata for the plugin's content authoring context.
     *
     * Returns null when the source LinkedIn post or its carousel slides
     * are missing — caller should mark Failed.
     */
    public function buildPluginInput(InstagramPost $draft): ?array
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
        // LinkedIn pipeline keeps EN preference (separate audience target). Fall
        // back to EN if ID missing for legacy posts authored before article-translate
        // pipeline shipped.
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

        // cross_post_targets — signals to plugin which OPTIONAL output fields to author.
        // When 'facebook' is present, plugin v0.3.0+ MUST author text_only_caption
        // for FacebookGenerationService to reuse on FB text posts.
        $crossPostTargets = $this->detectCrossPostTargets($linkedinPost);

        // Use branded shortener with IG UTM attribution (May 10, 2026).
        // IG caption body must NOT contain URL (schema rejects); blog_url is
        // consumed by `text_only_caption` (FB-reuse variant where body URL is OK).
        try {
            $blogUrl = app(\App\Services\ShortLinkService::class)
                ->forBlogPost($post, 'instagram');
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
                // blog_url consumed by text_only_caption when authored (FB body URL OK)
                'blog_url' => $blogUrl,
            ],
            'content_idea' => $this->buildContentIdeaPayload($post),
            'carousel_slides' => $this->normalizeSlides($slides),
            'format' => 'photo_carousel',
            'posting_time_options' => [], // Phase D scanner will pre-query posting_time_rules
            'cross_post_targets' => $crossPostTargets,
        ];
    }

    /**
     * Detect which downstream platforms reuse this IG output.
     *
     * Returns ['facebook'] when a FacebookPost sibling exists for the same
     * source LinkedInPost — plugin then authors text_only_caption that
     * FacebookGenerationService::generateText reads.
     *
     * @return array<int, string>
     */
    private function detectCrossPostTargets(\App\Models\LinkedInPost $linkedinPost): array
    {
        if (!$linkedinPost->relationLoaded('facebookPost')) {
            $linkedinPost->loadMissing('facebookPost');
        }
        $targets = [];
        if ($linkedinPost->facebookPost !== null) {
            $targets[] = 'facebook';
        }
        return $targets;
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

    /**
     * Normalize carousel_slides JSON into the shape the plugin expects.
     * LinkedIn pipeline stores extra fields the IG/TikTok plugins don't
     * need — pass through only the relevant ones.
     */
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
     * Persist plugin output + advance FSM.
     *
     * Routing:
     *   validation.passed=true  → Generating → AwaitingReview
     *   validation.passed=false → Generating → Failed (validation surfaced)
     *
     * @return array{success: bool, draft_id: int, status: string}
     */
    protected function persistAndRoute(InstagramPost $draft, array $parsed): array
    {
        $title = (string) ($parsed['title'] ?? '');
        $caption = (string) ($parsed['caption'] ?? '');
        // OPTIONAL — plugin v0.3.0+ authors this when input has cross_post_targets=['facebook'].
        // FacebookGenerationService::generateText reads it for FB text-post reuse.
        $textOnlyCaption = isset($parsed['text_only_caption']) && is_string($parsed['text_only_caption'])
            ? trim($parsed['text_only_caption'])
            : null;
        $hashtags = is_array($parsed['hashtags'] ?? null) ? $parsed['hashtags'] : [];
        $validation = is_array($parsed['validation'] ?? null) ? $parsed['validation'] : [];
        $passed = (bool) ($validation['passed'] ?? false);

        // Defense-in-depth — plugin Zod should reject these but service
        // double-checks (status='complete' could slip through with empty fields
        // if plugin schema regresses).
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
                'status' => InstagramPostStatus::Failed->value,
            ];
        }

        // Persist branded short URL for first-comment publishing (Publer comments[]
        // field, Phase H+ real impl). Format mirrors LinkedInPost.link_comment.
        // Skip the blog first-comment link for IG-repurpose drafts: their anchor
        // Post is unpublished (slide-gen only), so /blog/{slug} 404s. Empty
        // link_comment → Publer comments[] is empty → no dead-link first comment.
        $linkComment = null;
        if ($draft->post && !empty($draft->post->slug)
            && !\App\Models\RepurposeJob::isRepurposePost($draft->post_id, $draft->linkedin_post_id)) {
            try {
                $shortUrl = app(\App\Services\ShortLinkService::class)
                    ->forBlogPost($draft->post, 'instagram');
                $linkComment = "Full article: {$shortUrl}";
            } catch (\Throwable $e) {
                Log::warning('[InstagramGenerationService] short link generation failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $draft->update([
            'title' => $title,
            'caption' => $caption,
            'text_only_caption' => $textOnlyCaption !== '' ? $textOnlyCaption : null,
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
                'status' => InstagramPostStatus::Failed->value,
            ];
        }

        try {
            $this->guard->advance($draft, InstagramPostStatus::AwaitingReview, 'generation_complete');
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            Log::error('[InstagramGenerationService] FSM transition Generating→AwaitingReview failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
            ];
        }

        // Phase G — Telegram alert. Dormant by default (no setting row → no-op).
        $this->fireAwaitingReviewAlert($draft, $title, $caption);

        $this->maybeCascadeToPublisher($draft, InstagramPostStatus::Publishing, InstagramPost::class, $this->guard);

        return [
            'success' => true,
            'draft_id' => $draft->id,
            'status' => $draft->fresh()->status ?? InstagramPostStatus::AwaitingReview->value,
        ];
    }

    private function fireAwaitingReviewAlert(InstagramPost $draft, string $title, string $caption): void
    {
        try {
            App::make(\App\Services\TelegramNotificationService::class)
                ->sendCrossPostAwaitingReview('instagram', $draft->id, $title, $caption);
        } catch (\Throwable $e) {
            Log::warning('[InstagramGenerationService] Telegram alert failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function markFailed(InstagramPost $draft, string $reason): void
    {
        try {
            $this->guard->advance($draft, InstagramPostStatus::Failed, 'generation_failed', [
                'reason' => $reason,
            ]);
            $draft->update(['last_error' => $reason]);
        } catch (\Throwable $e) {
            Log::error('[InstagramGenerationService] markFailed transition itself failed', [
                'draft_id' => $draft->id,
                'reason' => $reason,
                'transition_error' => $e->getMessage(),
            ]);
        }

        // Phase G — Telegram alert. Dormant by default.
        try {
            App::make(\App\Services\TelegramNotificationService::class)
                ->sendCrossPostGenerationFailed('instagram', $draft->id, $reason);
        } catch (\Throwable $e) {
            Log::warning('[InstagramGenerationService] Telegram failed-alert errored', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
