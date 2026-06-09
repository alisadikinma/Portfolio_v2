<?php

namespace App\Services;

use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;

/**
 * Builds normalized Publer "post specs" for cross-post siblings.
 *
 * REWRITTEN June 10, 2026 against the LIVE-validated Publer bulk-publish
 * contract (probed end-to-end on the production workspace before shipping):
 *
 *   POST /api/v1/posts/schedule/publish
 *   {
 *     "bulk": {
 *       "state": "scheduled",
 *       "posts": [{
 *         "networks": { "<instagram|tiktok|threads|facebook>": {
 *           "type": "photo"|"status", "text": "...",
 *           "media": [{ "id": "<media_id>", "type": "image" }, ...]
 *         }},
 *         "accounts": [{ "id": "<account_id>", "scheduled_at": "" }],
 *         "comments": [{ "text": "...", "delay": {"duration":1,"unit":"minute"} }]
 *       }]
 *     }
 *   }
 *
 * KEY contract facts proven during the probe (do NOT regress):
 *   - The networks key MUST be the platform name (instagram/tiktok/...).
 *     Using the generic "default" key fails Instagram with
 *     "missing the social network params". (probe: default→FAIL, instagram→OK)
 *   - media[] references PRE-UPLOADED media ids (raw URLs are rejected). The
 *     job PublishViaPubler uploads each media URL via /media/from-url first,
 *     then injects the resulting media_ids — so THIS builder only returns the
 *     media *URLs* (in `media_urls`), NOT the final media[] objects.
 *   - post `type` = "photo" for an image (single OR multi-image carousel —
 *     Publer auto-builds the carousel from N photos), "status" for text-only.
 *   - `comments` live at the POST level (sibling of networks/accounts).
 *
 * Per-platform first-comment rules (CLAUDE.md May 10, 2026):
 *   - Instagram + Threads → comments[] carry the blog link (first-comment).
 *   - TikTok  → NO comments[] (no first-comment API — URL lives in caption body).
 *   - Facebook → NO comments[] (May 10 FB cleanup decision).
 *
 * Spec shape returned by each build* method:
 *   [
 *     'platform'       => 'instagram',
 *     'network'        => 'instagram',          // Publer networks[] key
 *     'account_id'     => '6a25...e83',
 *     'network_fields' => ['type'=>'photo','text'=>'...'],  // media injected later
 *     'media_urls'     => ['https://.../slide-01.png', ...], // to pre-upload
 *     'comments'       => [['text'=>..., 'delay'=>['duration'=>1,'unit'=>'minute']]],
 *   ]
 */
class PublerPayloadBuilder
{
    /** First-comment delay before Publer posts the blog link under the post. */
    private const COMMENT_DELAY = ['duration' => 1, 'unit' => 'minute'];

    // ─── Per-platform enabled gate ───────────────────────────────────────────

    /**
     * True when the operator has selected a Publer account for $platform
     * (setting `publer_{platform}_account_id` is non-empty).
     *
     * An empty account selection in the admin Publer settings = that platform
     * is DISABLED (e.g. operator left Threads unselected) → callers MUST skip
     * it rather than blindly publish (operator directive: "cek dulu settingan
     * nya ke sosmed mana saja.. jangan asal publish"). Re-selecting an account
     * later auto-reactivates the platform — no code change needed.
     *
     * Non-throwing — use this at dispatch sites to decide whether to enqueue.
     *
     * @param  string  $platform  'instagram'|'tiktok'|'threads'|'facebook'
     */
    public static function isPlatformEnabled(string $platform): bool
    {
        $value = Setting::where('group', 'publer')
            ->where('key', "publer_{$platform}_account_id")
            ->value('value');

        return is_string($value) && trim($value) !== '';
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    /** @throws \RuntimeException when publer_instagram_account_id is not set */
    public function buildInstagram(InstagramPost $sibling): array
    {
        return $this->spec(
            platform: 'instagram',
            accountId: $this->resolveAccountId('instagram'),
            text: $this->buildCaption($sibling->caption, $sibling->hashtags),
            mediaUrls: $this->buildMediaUrls($sibling),
            comments: $this->buildComments($sibling->link_comment),
        );
    }

    /**
     * TikTok does NOT support first-comment via Publer (confirmed May 10, 2026)
     * — the blog URL lives in the caption body. `link_comment` is stored for
     * parity but never sent. TikTok carries an optional native `title` (≤90).
     *
     * @throws \RuntimeException when publer_tiktok_account_id is not set
     */
    public function buildTiktok(TiktokPost $sibling): array
    {
        $extra = [];
        if (!empty($sibling->title)) {
            $extra['title'] = mb_substr($sibling->title, 0, 90);
        }

        return $this->spec(
            platform: 'tiktok',
            accountId: $this->resolveAccountId('tiktok'),
            text: $this->buildCaption($sibling->caption, $sibling->hashtags),
            mediaUrls: $this->buildMediaUrls($sibling),
            comments: [],
            networkExtra: $extra,
        );
    }

    /** @throws \RuntimeException when publer_threads_account_id is not set */
    public function buildThreads(ThreadsPost $sibling): array
    {
        return $this->spec(
            platform: 'threads',
            accountId: $this->resolveAccountId('threads'),
            text: $this->buildCaption($sibling->caption, $sibling->hashtags),
            mediaUrls: $this->buildMediaUrls($sibling),
            comments: $this->buildComments($sibling->link_comment),
        );
    }

    /**
     * Facebook receives NO comments[] (May 10, 2026 decision). Text format →
     * no media (link unfurl from caption); carousel → slide images.
     *
     * @throws \RuntimeException when publer_facebook_account_id is not set
     */
    public function buildFacebook(FacebookPost $sibling): array
    {
        $mediaUrls = ($sibling->format === 'carousel') ? $this->buildMediaUrls($sibling) : [];

        return $this->spec(
            platform: 'facebook',
            accountId: $this->resolveAccountId('facebook'),
            text: $this->buildCaption($sibling->caption, $sibling->hashtags),
            mediaUrls: $mediaUrls,
            comments: [],
        );
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Assemble the normalized spec. `type` is derived from media presence:
     * "photo" when there are image URLs (Publer auto-carousels multi-photo),
     * "status" for text-only.
     *
     * @param  array<int,string>  $mediaUrls
     * @param  array<int,array>   $comments
     * @param  array<string,mixed> $networkExtra  Extra network_fields (e.g. tiktok title)
     */
    private function spec(
        string $platform,
        string $accountId,
        string $text,
        array $mediaUrls,
        array $comments,
        array $networkExtra = [],
    ): array {
        $type = !empty($mediaUrls) ? 'photo' : 'status';

        $networkFields = array_merge([
            'type' => $type,
            'text' => $text,
        ], $networkExtra);

        return [
            'platform' => $platform,
            'network' => $platform,
            'account_id' => $accountId,
            'network_fields' => $networkFields,
            'media_urls' => $mediaUrls,
            'comments' => $comments,
        ];
    }

    /**
     * Resolve a Publer social account ID from settings group=publer.
     * Setting key: `publer_{platform}_account_id`.
     *
     * @throws \RuntimeException when missing/empty (gate should prevent reaching here)
     */
    private function resolveAccountId(string $platform): string
    {
        $value = Setting::where('group', 'publer')
            ->where('key', "publer_{$platform}_account_id")
            ->value('value');

        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException(
                "Publer account not configured for {$platform}. "
                . "Select an account via admin /admin/about → Publer Integration card."
            );
        }

        return trim($value);
    }

    /** Caption body + blank line + space-joined hashtags (hashtags optional). */
    private function buildCaption(?string $caption, ?array $hashtags): string
    {
        $body = $caption ?? '';

        if (!empty($hashtags)) {
            $body = rtrim($body) . "\n\n" . implode(' ', $hashtags);
        }

        return $body;
    }

    /**
     * Collect rendered carousel slide image URLs (the GeminiGen PNGs) from the
     * parent LinkedInPost, ordered by slide_number, skipping unrendered slides.
     *
     * @return array<int,string>
     */
    private function buildMediaUrls(object $sibling): array
    {
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

        usort($slides, fn ($a, $b) => ($a['slide_number'] ?? 0) <=> ($b['slide_number'] ?? 0));

        $urls = [];
        foreach ($slides as $slide) {
            $url = $slide['image_url'] ?? null;
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * First-comment payload (Instagram + Threads only). Empty when no link.
     *
     * @return array<int,array{text:string,delay:array{duration:int,unit:string}}>
     */
    private function buildComments(?string $linkComment): array
    {
        if (empty($linkComment)) {
            return [];
        }

        return [[
            'text' => $linkComment,
            'delay' => self::COMMENT_DELAY,
        ]];
    }
}
