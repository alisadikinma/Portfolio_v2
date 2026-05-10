<?php

namespace App\Services;

use App\Enums\ThreadsPostStatus;
use App\Models\ThreadsPost;
use Illuminate\Support\Facades\Log;

/**
 * Bridges Portfolio_v2 backend to the `social-short-form-writer` plugin's
 * `/threads-gen` skill (https://github.com/alisadikinma/social-short-form-writer).
 *
 * Plugin emits ONE JSON envelope to stdout matching `ThreadsOutputEnvelopeSchema`:
 *   { status: 'complete'|'failed',
 *     title: string (≤140 char preview-cut hook),
 *     caption: string (280-450 sweet, ≤500 hard cap),
 *     hashtags: string[] (0-3, max 3),
 *     language: 'id'|'en'|'mixed' (default 'id'),
 *     suggested_time_slot?: {...},
 *     validation: {...} }
 *
 * Per the plugin design, hard rules enforced by Zod:
 *   - hashtags 0-3 (Threads minimal hashtag culture, 4+ = spam signal)
 *   - caption ≤500 chars (Threads platform limit)
 *   - title ≤140 chars (preview-cut "more" cutoff)
 *   - NO URL in caption (Threads de-prioritizes body links)
 *
 * Tone: Pro-but-conversational (NEVER lowercase Gen-Z slang).
 * Language: ID+EN bilingual mix by default — hook in English (preview reach),
 * body mixed (cultural shorthand), engagement question in Indonesian.
 *
 * Execution is SYNCHRONOUS from this service's perspective — the plugin runs
 * ~30-60s on the VPS. Always invoke from a queued `GenerateThreadsPost` job.
 *
 * FSM: PendingGeneration|Failed|Cancelled → Generating → AwaitingReview
 *      (validation.passed=true) | Failed (validation.passed=false or any error)
 *
 * Upgraded from Tier-2 reuse (May 10 first commit) to Tier-1 native plugin
 * (May 10 second commit) — Gen Z target market justifies dedicated authoring
 * over LinkedIn-truncate-and-trim shortcut.
 */
class ThreadsGenerationService extends BaseSocialGenerationService
{
    protected string $skillName = 'threads-gen';
    protected string $refsConfigKey = 'social-cross-post.generation.refs_threads';

    public function __construct(
        private readonly PipelineGuard $guard,
    ) {
    }

    /**
     * @return array{success: bool, draft_id: int, status: string, error?: string|null}
     */
    public function generate(ThreadsPost $draft): array
    {
        if ($draft->status === ThreadsPostStatus::Generating->value) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => "Draft already in-flight (status={$draft->status})",
            ];
        }

        try {
            $this->guard->advance($draft, ThreadsPostStatus::Generating, 'plugin_dispatch_start');
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
            $this->markFailed($draft, 'Cannot build plugin input — LinkedIn post or blog translation missing');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => ThreadsPostStatus::Failed->value,
                'error' => 'Source LinkedIn post or translation missing',
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
                'status' => ThreadsPostStatus::Failed->value,
                'error' => $errMsg,
            ];
        }

        if (!$result['success']) {
            $errMsg = $result['error'] ?? 'Plugin invocation failed';
            $this->markFailed($draft, $errMsg);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => ThreadsPostStatus::Failed->value,
                'error' => $errMsg,
            ];
        }

        $parsed = $this->parseOrchestratorOutput($result['stdout']);
        if ($parsed === null) {
            $this->markFailed($draft, 'Could not parse /threads-gen JSON from stdout');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => ThreadsPostStatus::Failed->value,
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
                'status' => ThreadsPostStatus::Failed->value,
                'error' => (string) $errMsg,
            ];
        }

        return $this->persistAndRoute($draft, $parsed);
    }

    /**
     * Build the input payload shipped to the plugin via SSH.
     *
     * Threads accepts both text and carousel formats — pass slides when
     * format=carousel (LinkedIn carousel reuse) so the plugin can reference
     * slide context in the caption. For text format, slides[] is empty.
     */
    public function buildPluginInput(ThreadsPost $draft): ?array
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

        // Prefer ID translation (plugin v0.3.0+ authors Bahasa Indonesia by default —
        // language='id' default; was 'mixed' in v0.2.0). Fall back to EN for legacy
        // posts authored before article-translate pipeline shipped.
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

        $format = (string) ($draft->format ?? 'text');
        $slides = is_array($linkedinPost->carousel_slides ?? null)
            ? $linkedinPost->carousel_slides
            : [];

        return [
            'blog' => [
                'title' => $title,
                'content' => $this->stripHtmlToText((string) $translation->content),
                'excerpt' => $excerpt !== '' ? $excerpt : null,
                'meta_keywords' => $metaKeywords !== '' ? $metaKeywords : null,
                'slug' => $post->slug,
            ],
            'content_idea' => $this->buildContentIdeaPayload($post),
            'carousel_slides' => $format === 'carousel' ? $this->normalizeSlides($slides) : [],
            'format' => $format === 'carousel' ? 'photo_carousel' : 'text_only',
            'posting_time_options' => [],
            'language' => 'id', // Bahasa Indonesia by default (plugin v0.3.0+); 'en'/'mixed' available as override
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
     * Persist plugin output + advance FSM.
     *
     * Routing:
     *   validation.passed=true  → AwaitingReview (+ optional cascade to Publishing)
     *   validation.passed=false → Failed (validation surfaced via last_error)
     */
    protected function persistAndRoute(ThreadsPost $draft, array $parsed): array
    {
        $title = (string) ($parsed['title'] ?? '');
        $caption = (string) ($parsed['caption'] ?? '');
        $hashtags = is_array($parsed['hashtags'] ?? null) ? $parsed['hashtags'] : [];
        $validation = is_array($parsed['validation'] ?? null) ? $parsed['validation'] : [];
        $passed = (bool) ($validation['passed'] ?? false);

        // Persist branded short URL for first-comment publishing (Publer comments[]
        // field, Phase H+ real impl). Format mirrors LinkedInPost.link_comment.
        $linkComment = null;
        if ($draft->post && !empty($draft->post->slug)) {
            try {
                $shortUrl = app(\App\Services\ShortLinkService::class)
                    ->forBlogPost($draft->post, 'threads');
                $linkComment = "Full article: {$shortUrl}";
            } catch (\Throwable $e) {
                Log::warning('[ThreadsGenerationService] short link generation failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $draft->update([
            'title' => mb_substr($title, 0, 200),
            'caption' => $caption,
            'link_comment' => $linkComment,
            'hashtags' => array_slice(array_values(array_map('strval', $hashtags)), 0, 3),
        ]);

        if (!$passed) {
            $failures = is_array($validation['failures'] ?? null)
                ? implode('; ', array_map('strval', $validation['failures']))
                : 'Plugin validation reported passed=false without failure list';
            $this->markFailed($draft, "Validation failed: {$failures}");
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => ThreadsPostStatus::Failed->value,
                'error' => $failures,
            ];
        }

        try {
            $this->guard->advance($draft, ThreadsPostStatus::AwaitingReview, 'generation_complete');
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            Log::error('[ThreadsGenerationService] FSM transition Generating→AwaitingReview failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
            ];
        }

        // Publish-all cascade (May 10 ship). When parent LinkedIn flagged
        // auto_approve_cross_posts, advance AwaitingReview → Publishing
        // and dispatch Publer. Idempotent + non-fatal.
        $this->maybeCascadeToPublisher(
            $draft,
            ThreadsPostStatus::Publishing,
            ThreadsPost::class,
            $this->guard
        );

        return [
            'success' => true,
            'draft_id' => $draft->id,
            'status' => $draft->fresh()->status ?? ThreadsPostStatus::AwaitingReview->value,
        ];
    }

    private function markFailed(ThreadsPost $draft, string $reason): void
    {
        try {
            $draft->update(['last_error' => $reason]);
            $this->guard->advance($draft, ThreadsPostStatus::Failed, 'generation_failed', ['reason' => $reason]);
        } catch (\Throwable $e) {
            Log::error('[ThreadsGenerationService] markFailed transition failed', [
                'draft_id' => $draft->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
