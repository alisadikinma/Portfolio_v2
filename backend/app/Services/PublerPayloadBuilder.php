<?php

namespace App\Services;

use App\Models\FacebookPost;
use App\Models\ImageGenerationJob;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use RuntimeException;

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
        $images = $this->buildMediaUrls($sibling);
        $hookVideo = $this->resolveHookVideoUrl($sibling);

        if ($hookVideo !== null) {
            // Mixed carousel: GROK hook video item 0 + image slides (IG-only;
            // LinkedIn can't mix, TikTok stays all-image). Falls back to all-
            // image when the video isn't ready/app-hosted or the switch is off.
            $mediaUrls = array_merge([$hookVideo], $images);
            $mediaTypes = array_merge(['video'], array_fill(0, count($images), 'image'));
        } else {
            $mediaUrls = $images;
            $mediaTypes = array_fill(0, count($images), 'image');
        }

        return $this->spec(
            platform: 'instagram',
            accountId: $this->resolveAccountId('instagram'),
            text: $this->buildCaption($sibling->caption, $sibling->hashtags),
            mediaUrls: $mediaUrls,
            comments: $this->buildComments($sibling->link_comment),
            mediaTypes: $mediaTypes,
        );
    }

    /**
     * The IG hook video to prepend as carousel item 0, or null to fall back to
     * all-image. Gated by: the publer_ig_mixed_video_enabled kill-switch, the
     * video being done, and an app-hosted (Publer-ingestible) URL.
     */
    private function resolveHookVideoUrl(InstagramPost $sibling): ?string
    {
        if (! $this->isMixedVideoEnabled()) {
            return null;
        }
        if ($sibling->hook_video_status !== 'done') {
            return null;
        }

        return $this->isAppHostedUrl($sibling->hook_video_url)
            ? $sibling->hook_video_url
            : null;
    }

    /** Kill-switch (group=publer) — default ON; flip off if Publer rejects mixed. */
    private function isMixedVideoEnabled(): bool
    {
        $value = Setting::where('group', 'publer')
            ->where('key', 'publer_ig_mixed_video_enabled')
            ->value('value');

        if ($value === null) {
            return true; // default enabled
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
        array $mediaTypes = [],
    ): array {
        $type = !empty($mediaUrls) ? 'photo' : 'status';

        $networkFields = array_merge([
            'type' => $type,
            'text' => $text,
        ], $networkExtra);

        // Per-item media types parallel to media_urls (default all 'image').
        // PublishViaPubler reads these to tag each pre-uploaded media item.
        if ($mediaTypes === []) {
            $mediaTypes = array_fill(0, count($mediaUrls), 'image');
        }

        return [
            'platform' => $platform,
            'network' => $platform,
            'account_id' => $accountId,
            'network_fields' => $networkFields,
            'media_urls' => $mediaUrls,
            'media_types' => $mediaTypes,
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
            $urls[] = $this->resolveServableSlideUrl($linkedinPost, $slide);
        }

        return $urls;
    }

    /**
     * Resolve a single slide to a URL Publer can actually ingest.
     *
     * Publer's /media/from-url downloads the URL server-side and validates it
     * as an image. A raw GeminiGen edge URL (host edge-files.geminigen.ai) is
     * served with `Content-Type: application/octet-stream` AND is a short-lived
     * signed URL — Publer's media job "completes" with an EMPTY payload (no
     * media_id), which is the exact draft-149 failure. LinkedIn tolerated it
     * because publishCarousel fetches the bytes itself; Publer does not.
     *
     * Production slide image_urls are ALWAYS app-hosted
     * (https://alisadikinma.com/storage/linkedin-carousel/...) because they are
     * generated via url('/storage/...') after downloadAndStore mirrors the
     * remote PNG locally. So we hard-require the app host and, for legacy slides
     * that leaked a remote URL (download fell back to returning the remote URL),
     * recover the locally-mirrored URL from the slide's ImageGenerationJob row.
     * If neither yields an app-hosted URL we throw — a clear, actionable failure
     * beats silently feeding Publer a URL it can never fetch.
     */
    private function resolveServableSlideUrl(object $linkedinPost, array $slide): string
    {
        $url = $slide['image_url'] ?? null;
        if ($this->isAppHostedUrl($url)) {
            return $url;
        }

        $local = $this->localMirrorForSlide($linkedinPost, $slide);
        if ($local !== null) {
            return $local;
        }

        $n = $slide['slide_number'] ?? '?';
        throw new RuntimeException(
            "Cross-post blocked: carousel slide {$n} has no app-hosted image — only an "
            . 'external/expiring URL Publer cannot ingest. Re-render the slide, then retry.'
        );
    }

    /**
     * Recover the locally-mirrored (app-hosted) URL for a slide whose JSON
     * image_url leaked a remote URL. The slide's own image_job_uuid may point
     * at the failed-download job, so we key on slide_index (slide_number - 1)
     * and take the newest carousel_slide job that actually mirrored locally.
     */
    private function localMirrorForSlide(object $linkedinPost, array $slide): ?string
    {
        if (! isset($slide['slide_number'])) {
            return null;
        }

        $candidates = ImageGenerationJob::query()
            ->where('linkedin_post_id', $linkedinPost->id)
            ->where('type', 'carousel_slide')
            ->where('slide_index', (int) $slide['slide_number'] - 1)
            ->whereNotNull('image_url')
            ->orderByDesc('id')
            ->pluck('image_url');

        foreach ($candidates as $candidate) {
            if ($this->isAppHostedUrl($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * True when the URL is served from this app's own host (config app.url) —
     * i.e. a locally-mirrored /storage/ asset Publer can fetch as a real image.
     */
    private function isAppHostedUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if (is_string($appHost) && $appHost !== '' && is_string($urlHost) && $urlHost !== '') {
            return strcasecmp($appHost, $urlHost) === 0;
        }

        // Host-less relative path (e.g. /storage/...) is local by definition.
        return $urlHost === null && str_contains($url, '/storage/');
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
