<?php

namespace App\Services;

use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;

/**
 * Builds platform-specific Publer POST /posts/schedule request bodies.
 *
 * Each method returns a plain array matching Publer's bulk-schedule payload
 * shape. The job `PublishViaPubler` calls the appropriate method and hands
 * the result to `PublerClient::createPost()`.
 *
 * Per-platform rules (locked in CLAUDE.md May 10, 2026):
 *   - Instagram:  caption + hashtags, media[] from carousel_slides,
 *                 comments[] with link_comment + 30s delay
 *   - TikTok:     caption (URL already embedded in body — no first-comment API),
 *                 title field, media[] from carousel_slides, NO comments[]
 *   - Threads:    caption + hashtags, media[] from carousel_slides,
 *                 comments[] with link_comment + 30s delay
 *   - Facebook:   caption (± hashtags), media[] for carousel (empty for text),
 *                 NO comments[] (May 10 FB cleanup decision)
 *
 * Account IDs are read from settings group=publer. Missing ID → RuntimeException
 * so the job surfaces an actionable error rather than silently failing.
 */
class PublerPayloadBuilder
{
    // ─── Public API ────────────────────────────────────────────────────────────

    /**
     * Build Publer payload for an Instagram sibling draft.
     *
     * @throws \RuntimeException when publer_instagram_account_id is not set
     */
    public function buildInstagram(InstagramPost $sibling): array
    {
        $accountId = $this->resolveAccountId('instagram');

        $caption = $this->buildCaption($sibling->caption, $sibling->hashtags);
        $media = $this->buildMedia($sibling);
        $comments = $this->buildComments($sibling->link_comment);

        $payload = [
            'accounts' => [['id' => $accountId]],
            'caption' => $caption,
            'media' => $media,
            'scheduled_at' => $sibling->scheduled_at?->toIso8601String(),
        ];

        if (!empty($comments)) {
            $payload['comments'] = $comments;
        }

        return $payload;
    }

    /**
     * Build Publer payload for a TikTok sibling draft.
     *
     * TikTok does NOT support first-comment via Publer API (confirmed May 10,
     * 2026). URL lives in the caption body — `link_comment` column is populated
     * for parity but NOT passed to Publer.
     *
     * @throws \RuntimeException when publer_tiktok_account_id is not set
     */
    public function buildTiktok(TiktokPost $sibling): array
    {
        $accountId = $this->resolveAccountId('tiktok');

        $caption = $this->buildCaption($sibling->caption, $sibling->hashtags);
        $media = $this->buildMedia($sibling);

        $payload = [
            'accounts' => [['id' => $accountId]],
            'caption' => $caption,
            'media' => $media,
            'scheduled_at' => $sibling->scheduled_at?->toIso8601String(),
        ];

        // TikTok native title field (max 90 chars per Publer API spec).
        // Plugin emits it as $sibling->title (max 100 at DB; Publer enforces 90).
        if (!empty($sibling->title)) {
            $payload['title'] = mb_substr($sibling->title, 0, 90);
        }

        // NO comments[] — TikTok platform constraint, not Publer limitation.

        return $payload;
    }

    /**
     * Build Publer payload for a Threads sibling draft.
     *
     * @throws \RuntimeException when publer_threads_account_id is not set
     */
    public function buildThreads(ThreadsPost $sibling): array
    {
        $accountId = $this->resolveAccountId('threads');

        $caption = $this->buildCaption($sibling->caption, $sibling->hashtags);
        $media = $this->buildMedia($sibling);
        $comments = $this->buildComments($sibling->link_comment);

        $payload = [
            'accounts' => [['id' => $accountId]],
            'caption' => $caption,
            'media' => $media,
            'scheduled_at' => $sibling->scheduled_at?->toIso8601String(),
        ];

        if (!empty($comments)) {
            $payload['comments'] = $comments;
        }

        return $payload;
    }

    /**
     * Build Publer payload for a Facebook Page sibling draft.
     *
     * Facebook does NOT receive comments[] — the May 10, 2026 FB cleanup
     * decision removed link_comment from FB posts entirely (FB UI was hidden,
     * no Publer comments[] per the architectural decision to skip FB for now).
     *
     * For text format, media[] is empty (Publer/FB auto-unfurls link_url).
     * For carousel format, media[] carries slide PNGs.
     *
     * @throws \RuntimeException when publer_facebook_account_id is not set
     */
    public function buildFacebook(FacebookPost $sibling): array
    {
        $accountId = $this->resolveAccountId('facebook');

        $caption = $this->buildCaption($sibling->caption, $sibling->hashtags);

        // Carousel → include slide images; text → empty media (link unfurl)
        $media = ($sibling->format === 'carousel') ? $this->buildMedia($sibling) : [];

        $payload = [
            'accounts' => [['id' => $accountId]],
            'caption' => $caption,
            'media' => $media,
            'scheduled_at' => $sibling->scheduled_at?->toIso8601String(),
        ];

        // NO comments[] for Facebook (May 10, 2026 decision).

        return $payload;
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve a Publer social account ID from the `settings` table.
     *
     * Setting key pattern: `publer_{platform}_account_id` in group `publer`.
     *
     * @param  string  $platform  'instagram'|'tiktok'|'threads'|'facebook'
     * @throws \RuntimeException  when setting is missing or empty
     */
    private function resolveAccountId(string $platform): string
    {
        $key = "publer_{$platform}_account_id";

        $value = Setting::where('group', 'publer')
            ->where('key', $key)
            ->value('value');

        if (!is_string($value) || $value === '' || $value === null) {
            throw new \RuntimeException(
                "Publer account not configured for {$platform}. "
                . "Set '{$key}' via admin /admin/about → Publer Integration card."
            );
        }

        return $value;
    }

    /**
     * Build the caption string: body text + blank line + hashtags joined by space.
     * Hashtags may be null (no hashtag culture on FB/text posts).
     */
    private function buildCaption(?string $caption, ?array $hashtags): string
    {
        $body = $caption ?? '';

        if (!empty($hashtags)) {
            $tagLine = implode(' ', $hashtags);
            $body = rtrim($body) . "\n\n" . $tagLine;
        }

        return $body;
    }

    /**
     * Build media[] array from the parent LinkedInPost's carousel_slides.
     *
     * Reads `image_url` from each slide — these are the rendered GeminiGen PNGs
     * stored at `storage/app/public/linkedin-carousel/`. Skips slides without
     * an `image_url` (pending/failed renders). Sorted by `slide_number`.
     *
     * The sibling model must have `linkedinPost` relation loaded (or loadable).
     *
     * @return array<int, array{url: string}>
     */
    private function buildMedia(object $sibling): array
    {
        // Load relation if not already loaded
        if (!$sibling->relationLoaded('linkedinPost')) {
            $sibling->load('linkedinPost');
        }

        $linkedinPost = $sibling->linkedinPost;

        if ($linkedinPost === null) {
            return [];
        }

        $slides = $linkedinPost->carousel_slides ?? [];

        if (empty($slides)) {
            return [];
        }

        // Sort by slide_number (defensive — should already be ordered)
        usort($slides, fn ($a, $b) => ($a['slide_number'] ?? 0) <=> ($b['slide_number'] ?? 0));

        $media = [];
        foreach ($slides as $slide) {
            $url = $slide['image_url'] ?? null;
            if (is_string($url) && $url !== '') {
                $media[] = ['url' => $url];
            }
        }

        return $media;
    }

    /**
     * Build comments[] payload for platforms that support first-comment.
     * (Instagram + Threads — NOT TikTok, NOT Facebook.)
     *
     * Returns an empty array when link_comment is null/empty so callers
     * can use `if (!empty($comments))` guard cleanly.
     *
     * @return array<int, array{text: string, delay: array{duration: int, unit: string}}>
     */
    private function buildComments(?string $linkComment): array
    {
        if (empty($linkComment)) {
            return [];
        }

        return [
            [
                'text' => $linkComment,
                'delay' => [
                    'duration' => 30,
                    'unit' => 'seconds',
                ],
            ],
        ];
    }
}
