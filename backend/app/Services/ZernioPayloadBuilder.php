<?php

namespace App\Services;

use App\Models\ImageGenerationJob;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Builds Zernio create-post bodies for cross-post siblings.
 *
 * Zernio's POST /v1/posts contract (verified 2026-06-15):
 *   {
 *     "content": "<caption + hashtags>",
 *     "mediaItems": [{ "url": "https://...png", "type": "image"|"video" }, ...],
 *     "platforms": [{
 *       "platform": "instagram"|"tiktok"|"threads",
 *       "accountId": "<workspace account id>",
 *       "platformSpecificData": { "firstComment": "<link>" }   // IG only
 *     }]
 *     // publishNow / scheduledFor + timezone are added by PublishViaZernio,
 *     // NOT here — this builder stays pure (content only).
 *   }
 *
 * Differences vs PublerPayloadBuilder:
 *   - Zernio takes PUBLIC CDN URLs directly in mediaItems (no pre-upload). We
 *     still require app-hosted URLs (Zernio fetches server-side and rejects
 *     Google Drive/Dropbox/expiring edge URLs the same way Publer did), reusing
 *     the same app-host + local-mirror recovery logic.
 *   - IG mixed video+image carousel is NATIVE on Zernio (the thing Publer
 *     couldn't do), so the hook video is prepended whenever it's ready — NO
 *     kill-switch. First item sets the carousel aspect ratio (must be 4:5 —
 *     verified live in Phase J).
 *   - IG first comment = platformSpecificData.firstComment (native field).
 *     TikTok has no first-comment (link in caption). Threads firstComment is
 *     NOT a createPost field on Zernio (reply-only) → v1 omits it.
 *
 * Account-id gate mirrors Publer: a sibling whose zernio_{platform}_account_id
 * is empty throws (the PublisherResolver/dispatch gate should prevent reaching
 * here, but we fail loud rather than publish to the wrong/empty account).
 */
class ZernioPayloadBuilder
{
    /** Instagram: ≤10 carousel items (incl. an optional leading hook video). */
    private const IG_MAX_ITEMS = 10;

    /** TikTok: ≤35 photos (image-only — no mixing). */
    private const TIKTOK_MAX_PHOTOS = 35;

    /** Threads: ≤10 images, NO video carousel, 500-char hard caption cap. */
    private const THREADS_MAX_IMAGES = 10;

    private const THREADS_CHAR_LIMIT = 500;

    // ─── Per-platform enabled gate ───────────────────────────────────────────

    /**
     * True when the operator has entered a Zernio account id for $platform
     * (setting zernio_{platform}_account_id non-empty). Non-throwing — use at
     * dispatch sites to decide whether to enqueue.
     */
    public static function isPlatformEnabled(string $platform): bool
    {
        $value = Setting::where('group', 'zernio')
            ->where('key', "zernio_{$platform}_account_id")
            ->value('value');

        return is_string($value) && trim($value) !== '';
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Instagram: mixed video+image carousel. The GROK hook video (when done +
     * app-hosted) leads as mediaItems[0]; blog link rides in firstComment.
     */
    public function buildInstagram(InstagramPost $sibling): array
    {
        $images = $this->slideMediaItems($sibling);
        $hookVideo = $this->resolveHookVideoUrl($sibling);

        $mediaItems = $hookVideo !== null
            ? array_merge([['url' => $hookVideo, 'type' => 'video']], $images)
            : $images;

        $mediaItems = array_slice($mediaItems, 0, self::IG_MAX_ITEMS);

        $platformSpecificData = [];
        if (! empty($sibling->link_comment)) {
            $platformSpecificData['firstComment'] = $sibling->link_comment;
        }

        return $this->payload(
            platform: 'instagram',
            accountId: $this->resolveAccountId('instagram'),
            content: $this->buildCaption($sibling->caption, $sibling->hashtags),
            mediaItems: $mediaItems,
            platformSpecificData: $platformSpecificData,
        );
    }

    /**
     * TikTok: image-only photo carousel (Zernio forbids mixing photos+video),
     * capped at 35. No first comment (TikTok has no first-comment API).
     */
    public function buildTiktok(TiktokPost $sibling): array
    {
        $images = array_slice($this->slideMediaItems($sibling), 0, self::TIKTOK_MAX_PHOTOS);

        return $this->payload(
            platform: 'tiktok',
            accountId: $this->resolveAccountId('tiktok'),
            content: $this->buildCaption($sibling->caption, $sibling->hashtags),
            mediaItems: $images,
        );
    }

    /**
     * Threads: image-only (no video carousel), capped 10. Caption hard-capped
     * at 500 chars — Threads' #1 failure mode is over-length text. /threads-gen
     * authors short already; this is a defensive guard.
     */
    public function buildThreads(ThreadsPost $sibling): array
    {
        $images = array_slice($this->slideMediaItems($sibling), 0, self::THREADS_MAX_IMAGES);
        $content = $this->capThreadsContent(
            $this->buildCaption($sibling->caption, $sibling->hashtags),
            (int) ($sibling->id ?? 0)
        );

        return $this->payload(
            platform: 'threads',
            accountId: $this->resolveAccountId('threads'),
            content: $content,
            mediaItems: $images,
        );
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /** Assemble the single-platform Zernio create-post body (sans scheduling). */
    private function payload(
        string $platform,
        string $accountId,
        string $content,
        array $mediaItems,
        array $platformSpecificData = [],
    ): array {
        $platformEntry = [
            'platform' => $platform,
            'accountId' => $accountId,
        ];
        if ($platformSpecificData !== []) {
            $platformEntry['platformSpecificData'] = $platformSpecificData;
        }

        return [
            'content' => $content,
            'mediaItems' => array_values($mediaItems),
            'platforms' => [$platformEntry],
        ];
    }

    /**
     * The IG hook video to lead the carousel, or null. Gated only on the video
     * being done + app-hosted (Zernio CAN publish mixed IG carousels — no
     * kill-switch, unlike the Publer path).
     */
    private function resolveHookVideoUrl(InstagramPost $sibling): ?string
    {
        if ($sibling->hook_video_status !== 'done') {
            return null;
        }

        return $this->isAppHostedUrl($sibling->hook_video_url)
            ? $sibling->hook_video_url
            : null;
    }

    /**
     * Carousel slide images as Zernio mediaItems ({url,type:image}), ordered by
     * slide_number, each resolved to an app-hosted (Zernio-ingestible) URL.
     *
     * @return array<int,array{url:string,type:string}>
     */
    private function slideMediaItems(object $sibling): array
    {
        if (! $sibling->relationLoaded('linkedinPost')) {
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

        $items = [];
        foreach ($slides as $slide) {
            $items[] = ['url' => $this->resolveServableSlideUrl($linkedinPost, $slide), 'type' => 'image'];
        }

        return $items;
    }

    /**
     * Resolve a slide to a URL Zernio can fetch. Production slide image_urls are
     * app-hosted (https://alisadikinma.com/storage/...). For legacy slides that
     * leaked a remote/expiring URL, recover the locally-mirrored URL from the
     * slide's ImageGenerationJob. Throw if neither yields an app-hosted URL —
     * a clear, actionable failure beats feeding Zernio a URL it can never fetch.
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
            .'external/expiring URL Zernio cannot ingest. Re-render the slide, then retry.'
        );
    }

    /** Recover the app-hosted mirror URL for a slide whose JSON leaked a remote URL. */
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

    /** True when the URL is served from this app's own host (a /storage mirror). */
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

        return $urlHost === null && str_contains($url, '/storage/');
    }

    /**
     * Resolve a Zernio account id from settings group=zernio.
     *
     * @throws RuntimeException when missing/empty (gate should prevent reaching here)
     */
    private function resolveAccountId(string $platform): string
    {
        $value = Setting::where('group', 'zernio')
            ->where('key', "zernio_{$platform}_account_id")
            ->value('value');

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                "Zernio account not configured for {$platform}. "
                .'Enter the account id via /admin/about → Zernio Publishing card.'
            );
        }

        return trim($value);
    }

    /** Caption body + blank line + space-joined hashtags (hashtags optional). */
    private function buildCaption(?string $caption, ?array $hashtags): string
    {
        $body = $caption ?? '';

        if (! empty($hashtags)) {
            $body = rtrim($body)."\n\n".implode(' ', $hashtags);
        }

        return $body;
    }

    /** Hard-cap Threads content at 500 chars, logging when truncation happens. */
    private function capThreadsContent(string $content, int $siblingId): string
    {
        if (mb_strlen($content) <= self::THREADS_CHAR_LIMIT) {
            return $content;
        }

        Log::warning("Zernio Threads caption truncated to 500 chars (threads_post #{$siblingId})");

        return mb_substr($content, 0, self::THREADS_CHAR_LIMIT);
    }
}
