<?php

namespace App\Services;

use App\Jobs\PostLinkedInFirstComment;
use App\Models\LinkedInAccount;
use App\Models\LinkedInPost;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Wraps LinkedIn REST API v2 publish endpoints. Per plugin Addendum 3 §13.2:
 *   - publishText(): POST /v2/ugcPosts with shareContent (text post)
 *   - publishCarousel(): 3-step register upload → upload bytes → create UGC post
 *     (deferred — requires PDF composition via TCPDF, plugin Phase D11)
 *
 * Return shape: ['success' => bool, 'post_urn' => ?string, 'post_url' => ?string, 'error' => ?string]
 */
class LinkedInPublishService
{
    public function __construct(private readonly LinkedInOAuthService $oauth)
    {
    }

    /**
     * True when: OAuth app configured + at least one connected account
     * with a non-expired access_token exists.
     */
    public function isReady(): bool
    {
        if (empty(config('linkedin.oauth.client_id')) || empty(config('linkedin.oauth.client_secret'))) {
            return false;
        }
        return LinkedInAccount::where('access_token_expires_at', '>', now())->exists();
    }

    /**
     * Top-level dispatch — routes to publishText or publishCarousel
     * based on the draft's format.
     */
    public function publish(LinkedInPost $draft): array
    {
        if (!$this->isReady()) {
            return [
                'success' => false,
                'post_urn' => null,
                'post_url' => null,
                'error' => 'LinkedIn publisher not ready: OAuth not configured or no valid account connected',
            ];
        }

        // Circuit breaker — after a 429 from LinkedIn, the quota-pause setting
        // is written 24h forward. While active, every publish call is a no-op
        // that returns success=false with a descriptive error. Prevents the
        // social:publish-slot cron from continuing to hammer the API after
        // quota exhaustion — the production incident 2026-05-13 was caused by
        // 3 stuck awaiting_publish drafts × every-minute cron = 180 wasted
        // 429 calls per hour, deepening the quota deficit and blocking any
        // chance of partial recovery within the 24h reset window.
        if ($pauseUntil = $this->quotaPauseActiveUntil()) {
            return [
                'success' => false,
                'post_urn' => null,
                'post_url' => null,
                'error' => "LinkedIn quota paused until {$pauseUntil->toIso8601String()} (24h after last 429). Operator can clear setting linkedin_quota_pause_until to override.",
            ];
        }

        $account = $draft->account
            ?? LinkedInAccount::where('access_token_expires_at', '>', now())->latest()->first();

        if ($account === null) {
            return [
                'success' => false,
                'post_urn' => null,
                'post_url' => null,
                'error' => 'No connected LinkedIn account available',
            ];
        }

        // Refresh token if expired
        if ($account->isAccessTokenExpired()) {
            try {
                $account = $this->oauth->refreshAccount($account);
            } catch (RuntimeException $e) {
                return [
                    'success' => false,
                    'post_urn' => null,
                    'post_url' => null,
                    'error' => 'Token refresh failed: ' . $e->getMessage(),
                ];
            }
        }

        // Defense-in-depth — legacy drafts (pre-Apr 29 2026) may have a
        // non-URL link_comment. Rewrite to "Full article: {blogUrl}" so
        // the first-comment job posts the actual blog link, not whatever
        // the plugin happened to emit before resolveLinkComment shipped.
        $draft->loadMissing('post');
        if ($draft->ensureLinkCommentHasUrl()) {
            Log::info('[LinkedInPublish] Rewrote legacy link_comment to include blog URL', [
                'draft_id' => $draft->id,
            ]);
        }

        return match ($draft->format) {
            'carousel' => $this->publishCarousel($draft, $account),
            default => $this->publishText($draft, $account),
        };
    }

    /**
     * Publish a plain text post via POST /v2/ugcPosts.
     *
     * LinkedIn UGC posts API (v2 share pattern):
     *   POST https://api.linkedin.com/v2/ugcPosts
     *   Authorization: Bearer <access_token>
     *   X-Restli-Protocol-Version: 2.0.0
     *   Body: {
     *     author: "urn:li:person:{id}",
     *     lifecycleState: "PUBLISHED",
     *     specificContent: {
     *       "com.linkedin.ugc.ShareContent": {
     *         shareCommentary: { text: "..." },
     *         shareMediaCategory: "NONE"
     *       }
     *     },
     *     visibility: { "com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC" }
     *   }
     */
    public function publishText(LinkedInPost $draft, LinkedInAccount $account): array
    {
        $body = $this->composeTextBody($draft);
        if ($body === '') {
            return [
                'success' => false,
                'post_urn' => null,
                'post_url' => null,
                'error' => 'Cannot publish: draft content is empty',
            ];
        }

        $baseUrl = rtrim((string) config('linkedin.api.base_url', 'https://api.linkedin.com/v2'), '/');
        $endpoint = $baseUrl . (string) config('linkedin.api.ugc_posts_endpoint', '/ugcPosts');

        $author = $this->authorUrn($account);

        // Resolve thumbnail asset URN. Reuse blog's featured_image (already 16:9,
        // CDN-served, operator-approved). Pure text posts ("wall of text")
        // tank reach on LinkedIn — IMAGE-category posts with a thumbnail get
        // ~2x the dwell time. Idempotent via stored thumbnail_asset_urn so
        // publish-now retries don't double-upload.
        $draft->loadMissing(['post.translations']);
        $thumbnailUrn = $this->resolveThumbnailAssetUrn($draft, $account);
        $blogTitle = (string) ($draft->post?->translations->first()?->title ?? '');

        $shareContent = $thumbnailUrn !== null
            ? [
                'shareCommentary' => ['text' => $body],
                'shareMediaCategory' => 'IMAGE',
                'media' => [[
                    'status' => 'READY',
                    'media' => $thumbnailUrn,
                    'title' => ['text' => mb_substr($blogTitle ?: 'Blog post', 0, 200)],
                ]],
            ]
            : [
                'shareCommentary' => ['text' => $body],
                'shareMediaCategory' => 'NONE',
            ];

        $payload = [
            'author' => $author,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => $shareContent,
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        try {
            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error('[LinkedInPublish] publishText HTTP exception', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'post_urn' => null,
                'post_url' => null,
                'error' => 'Network error calling LinkedIn: ' . $e->getMessage(),
            ];
        }

        if (!$response->successful()) {
            Log::warning('[LinkedInPublish] publishText API error', [
                'draft_id' => $draft->id,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);
            if ($response->status() === 429) {
                $this->activateQuotaPause('publishText', $draft->id);
            }
            return [
                'success' => false,
                'post_urn' => null,
                'post_url' => null,
                'error' => "LinkedIn API {$response->status()}: " . $this->extractApiError($response),
            ];
        }

        // LinkedIn returns the URN in the X-RestLi-Id header OR in the body.
        $postUrn = $response->header('X-RestLi-Id') ?: ($response->json('id'));
        $postUrl = $this->urnToPublicUrl($postUrn);

        Log::info('[LinkedInPublish] publishText success', [
            'draft_id' => $draft->id,
            'post_urn' => $postUrn,
        ]);

        // Schedule first-comment (blog link) when configured
        if ($this->firstCommentEnabled($draft) && $postUrn) {
            $delay = (int) config('linkedin.first_comment_delay_seconds', 30);
            PostLinkedInFirstComment::dispatch($draft->id, $postUrn, $account->id)
                ->delay(now()->addSeconds(max(5, $delay)));
        }

        return [
            'success' => true,
            'post_urn' => $postUrn,
            'post_url' => $postUrl,
            'error' => null,
        ];
    }

    /**
     * Publish a carousel as a LinkedIn multi-image post. MVP path while the
     * proper TCPDF document carousel is deferred. Visually similar to a
     * native PDF carousel — viewer swipes through the rendered slides as
     * a multi-image gallery.
     *
     * Flow:
     *   1. Validate every slide has image_status=done + image_url
     *   2. Cap at 9 slides (LinkedIn ugcPost media[] limit)
     *   3. For each slide where slide_asset_urns[i] is missing →
     *      registerAndUploadImage(image_url, account) → persist URN
     *      (idempotent: 7-of-9 partial uploads survive across retries)
     *   4. Compose ugcPost payload with shareMediaCategory=IMAGE +
     *      media[] array of all asset URNs
     *   5. POST /v2/ugcPosts → extract URN from X-RestLi-Id header
     *   6. Schedule first-comment (blog link) when configured
     *
     * Note: LinkedIn shows multi-image posts as a swipeable gallery in
     * feed; engagement is ~70-80% of true PDF document carousels but
     * MVP gets us live publishing today. PDF flow (linkedin_asset_urn +
     * shareMediaCategory=DOCUMENT) ships separately.
     */
    public function publishCarousel(LinkedInPost $draft, LinkedInAccount $account): array
    {
        $slides = is_array($draft->carousel_slides) ? $draft->carousel_slides : [];
        $slideCount = count($slides);

        if ($slideCount === 0) {
            return $this->carouselFailure($draft, 'Carousel has no slides — regenerate before publishing.');
        }

        if ($slideCount > 9) {
            return $this->carouselFailure($draft, "LinkedIn allows max 9 images per multi-image post; this carousel has {$slideCount}. Reduce slides or wait for PDF carousel support.");
        }

        // Validate every slide has a rendered image ready to upload.
        foreach ($slides as $i => $slide) {
            $status = $slide['image_status'] ?? null;
            $url = trim((string) ($slide['image_url'] ?? ''));
            if ($status !== 'done' || $url === '') {
                $human = $i + 1;
                return $this->carouselFailure(
                    $draft,
                    "Slide {$human} not ready (status={$status}, image_url=" . ($url === '' ? 'empty' : 'present') . "). Regenerate failed slides before publishing."
                );
            }
        }

        // Resolve / upload asset URNs (idempotent — preserves prior partial uploads).
        $persisted = is_array($draft->slide_asset_urns) ? $draft->slide_asset_urns : [];
        $resolved = $persisted;
        $changed = false;

        foreach ($slides as $i => $slide) {
            $key = (string) $i;
            if (!empty($resolved[$key])) {
                continue; // already uploaded in a prior attempt
            }
            $sourceUrl = trim((string) $slide['image_url']);
            $urn = $this->registerAndUploadImage($sourceUrl, $account);
            if ($urn === null) {
                // Persist whatever succeeded so far before bailing.
                if ($changed) {
                    $draft->update(['slide_asset_urns' => $resolved]);
                }
                $human = $i + 1;
                return $this->carouselFailure(
                    $draft,
                    "Failed to upload slide {$human} to LinkedIn. Retry publish to resume from this slide.",
                    /* persistError */ true
                );
            }
            $resolved[$key] = $urn;
            $changed = true;
        }

        if ($changed) {
            $draft->update(['slide_asset_urns' => $resolved]);
        }

        // Compose payload. media[] order MUST follow slide_number — viewers
        // swipe in array order so cover (slide 1) must be first.
        $draft->loadMissing(['post.translations']);
        $blogTitle = (string) ($draft->post?->translations->first()?->title ?? 'Carousel');
        $titleText = mb_substr($blogTitle, 0, 200);

        $media = [];
        foreach ($slides as $i => $slide) {
            $key = (string) $i;
            if (empty($resolved[$key])) {
                // Defensive — should never happen given the loop above.
                return $this->carouselFailure($draft, "Slide " . ($i + 1) . " has no asset URN after upload phase. This is a bug; please report.");
            }
            $media[] = [
                'status' => 'READY',
                'media' => $resolved[$key],
                'title' => ['text' => $titleText],
            ];
        }

        $body = $this->composeTextBody($draft);
        if ($body === '') {
            return $this->carouselFailure($draft, 'Cannot publish: caption is empty. Edit content before publishing.');
        }

        $author = $this->authorUrn($account);
        $payload = [
            'author' => $author,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $body],
                    'shareMediaCategory' => 'IMAGE',
                    'media' => $media,
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $baseUrl = rtrim((string) config('linkedin.api.base_url', 'https://api.linkedin.com/v2'), '/');
        $endpoint = $baseUrl . (string) config('linkedin.api.ugc_posts_endpoint', '/ugcPosts');

        try {
            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(45)
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error('[LinkedInPublish] publishCarousel HTTP exception', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return $this->carouselFailure($draft, 'Network error calling LinkedIn: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            Log::warning('[LinkedInPublish] publishCarousel API error', [
                'draft_id' => $draft->id,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);
            if ($response->status() === 429) {
                $this->activateQuotaPause('publishCarousel', $draft->id);
            }
            return $this->carouselFailure(
                $draft,
                "LinkedIn API {$response->status()}: " . $this->extractApiError($response)
            );
        }

        $postUrn = $response->header('X-RestLi-Id') ?: ($response->json('id'));
        $postUrl = $this->urnToPublicUrl($postUrn);

        Log::info('[LinkedInPublish] publishCarousel success', [
            'draft_id' => $draft->id,
            'post_urn' => $postUrn,
            'slide_count' => $slideCount,
        ]);

        if ($this->firstCommentEnabled($draft) && $postUrn) {
            $delay = (int) config('linkedin.first_comment_delay_seconds', 30);
            PostLinkedInFirstComment::dispatch($draft->id, $postUrn, $account->id)
                ->delay(now()->addSeconds(max(5, $delay)));
        }

        return [
            'success' => true,
            'post_urn' => $postUrn,
            'post_url' => $postUrl,
            'error' => null,
        ];
    }

    /**
     * Uniform failure return + optional last_error persistence for
     * publishCarousel branches. Keeping this helper inline avoids
     * scattering identical 4-key arrays across 8+ early-exit paths.
     */
    private function carouselFailure(LinkedInPost $draft, string $message, bool $persistError = false): array
    {
        if ($persistError) {
            try {
                $draft->update(['last_error' => $message]);
            } catch (\Throwable $e) {
                // Non-fatal — surface the original error to the caller.
            }
        }
        return [
            'success' => false,
            'post_urn' => null,
            'post_url' => null,
            'error' => $message,
        ];
    }

    /**
     * Post a first-comment with the blog link on a published UGC post.
     * Avoids the 60% reach penalty from external links in post body.
     *
     * POST /v2/socialActions/{postUrn}/comments
     *   Body: { actor: "urn:li:person:{id}", message: { text: "..." } }
     */
    public function postFirstComment(string $postUrn, string $linkComment, LinkedInAccount $account): array
    {
        $baseUrl = rtrim((string) config('linkedin.api.base_url', 'https://api.linkedin.com/v2'), '/');
        // URL-encode the postUrn segment — it contains `:` which LinkedIn
        // treats as path-safe but we escape to be defensive.
        $endpoint = $baseUrl . '/socialActions/' . rawurlencode($postUrn) . '/comments';

        $payload = [
            'actor' => $this->authorUrn($account),
            'object' => $postUrn,
            'message' => ['text' => $linkComment],
        ];

        try {
            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(20)
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Network error: ' . $e->getMessage()];
        }

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => "LinkedIn API {$response->status()}: " . $this->extractApiError($response),
            ];
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * Compose the final body text. For text posts: content as-is +
     * 2 blank lines + hashtags joined by space. Empty hashtags just emit
     * the content.
     */
    private function composeTextBody(LinkedInPost $draft): string
    {
        $content = trim((string) $draft->content);
        if ($content === '') {
            return '';
        }

        $hashtags = is_array($draft->hashtags) ? $draft->hashtags : [];
        $hashtagsLine = collect($hashtags)
            ->filter(fn ($h) => is_string($h) && trim($h) !== '')
            ->map(fn ($h) => str_starts_with(trim($h), '#') ? trim($h) : '#' . trim($h))
            ->implode(' ');

        return $hashtagsLine !== ''
            ? $content . "\n\n" . $hashtagsLine
            : $content;
    }

    /**
     * Derive a public LinkedIn URL from the post URN. LinkedIn URN format:
     *   urn:li:ugcPost:<activity-id>  OR  urn:li:share:<activity-id>
     * Public URL pattern: https://www.linkedin.com/feed/update/<urn>/
     */
    private function urnToPublicUrl(?string $urn): ?string
    {
        if (!$urn) {
            return null;
        }
        return 'https://www.linkedin.com/feed/update/' . $urn . '/';
    }

    /**
     * Resolve the author/owner URN for LinkedIn API payloads. Handles all
     * three storage formats we've seen for LinkedInAccount.person_urn:
     *
     *   - "urn:li:person:aYm_YQ3pM7" — current OpenID Connect format (sub from /userinfo)
     *   - "urn:li:member:599804594" — legacy r_liteprofile format
     *   - "aYm_YQ3pM7" — bare ID (defensive — should not happen post v0.5.0)
     *
     * If already URN-prefixed (`urn:li:`), returns as-is. Otherwise prepends
     * `urn:li:person:` (the OIDC default). Prevents the "double-prefix" bug
     * that produced authors like `urn:li:person:urn:li:member:599804594`
     * which LinkedIn rejected with /author regex mismatch.
     */
    private function authorUrn(LinkedInAccount $account): string
    {
        $stored = trim((string) $account->person_urn);
        if (str_starts_with($stored, 'urn:li:')) {
            return $stored;
        }
        return "urn:li:person:{$stored}";
    }

    private function firstCommentEnabled(LinkedInPost $draft): bool
    {
        // Global env default
        $globalEnabled = (bool) config('linkedin.first_comment_enabled', true);
        if (!$globalEnabled) {
            return false;
        }
        // Need a link_comment to post
        return !empty(trim((string) $draft->link_comment));
    }

    private function extractApiError(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            return (string) ($json['message'] ?? $json['error_description'] ?? $json['error'] ?? $response->body());
        }
        return mb_substr((string) $response->body(), 0, 300);
    }

    /**
     * Resolve the thumbnail asset URN for a text-format draft. Returns the
     * existing `thumbnail_asset_urn` if already uploaded, otherwise tries to
     * upload the blog's `posts.featured_image` to LinkedIn DigitalMedia and
     * persists the URN. Returns null on any failure (caller falls back to
     * shareMediaCategory=NONE — a thumbnail-less post still publishes).
     */
    private function resolveThumbnailAssetUrn(LinkedInPost $draft, LinkedInAccount $account): ?string
    {
        $existing = trim((string) ($draft->thumbnail_asset_urn ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $featuredImage = trim((string) ($draft->post?->featured_image ?? ''));
        if ($featuredImage === '') {
            return null;
        }

        $assetUrn = $this->registerAndUploadImage($featuredImage, $account);
        if ($assetUrn === null) {
            return null;
        }

        $draft->update(['thumbnail_asset_urn' => $assetUrn]);
        return $assetUrn;
    }

    /**
     * 3-step LinkedIn DigitalMedia image upload:
     *   1. POST /v2/assets?action=registerUpload — get uploadUrl + asset URN
     *   2. GET source bytes from $sourceImageUrl
     *   3. PUT bytes to uploadUrl with Bearer auth
     *
     * Returns the asset URN on success, null on any failure (logged).
     * The asset URN can be used in UGC post payloads as media[].media.
     */
    public function registerAndUploadImage(string $sourceImageUrl, LinkedInAccount $account): ?string
    {
        $baseUrl = rtrim((string) config('linkedin.api.base_url', 'https://api.linkedin.com/v2'), '/');
        $registerUrl = $baseUrl . '/assets?action=registerUpload';

        $registerPayload = [
            'registerUploadRequest' => [
                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                'owner' => $this->authorUrn($account),
                'serviceRelationships' => [[
                    'relationshipType' => 'OWNER',
                    'identifier' => 'urn:li:userGeneratedContent',
                ]],
            ],
        ];

        // Step 1: register upload
        try {
            $registerResponse = Http::withToken($account->access_token)
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type' => 'application/json',
                    'LinkedIn-Version' => (string) config('linkedin.api.version', '202405'),
                ])
                ->timeout(30)
                ->post($registerUrl, $registerPayload);
        } catch (\Throwable $e) {
            Log::error('[LinkedInPublish] registerUpload exception', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$registerResponse->successful()) {
            Log::warning('[LinkedInPublish] registerUpload failed', [
                'status' => $registerResponse->status(),
                'body' => mb_substr((string) $registerResponse->body(), 0, 300),
            ]);
            if ($registerResponse->status() === 429) {
                $this->activateQuotaPause('registerUpload', null);
            }
            return null;
        }

        $registerJson = $registerResponse->json();
        $mechanism = $registerJson['value']['uploadMechanism'] ?? [];
        // Note: LinkedIn key contains literal dots, so direct array access only.
        $mediaUploadKey = 'com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest';
        $uploadUrl = $mechanism[$mediaUploadKey]['uploadUrl'] ?? null;
        $assetUrn = $registerJson['value']['asset'] ?? null;

        if (!$uploadUrl || !$assetUrn) {
            Log::warning('[LinkedInPublish] registerUpload missing uploadUrl or asset', [
                'response' => $registerJson,
            ]);
            return null;
        }

        // Step 2: fetch source image bytes
        try {
            $imageResponse = Http::timeout(30)->get($sourceImageUrl);
        } catch (\Throwable $e) {
            Log::error('[LinkedInPublish] image source fetch exception', [
                'url' => $sourceImageUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!$imageResponse->successful()) {
            Log::warning('[LinkedInPublish] image source fetch failed', [
                'url' => $sourceImageUrl,
                'status' => $imageResponse->status(),
            ]);
            return null;
        }

        $imageBytes = $imageResponse->body();
        if (strlen($imageBytes) === 0) {
            Log::warning('[LinkedInPublish] image source returned empty bytes', ['url' => $sourceImageUrl]);
            return null;
        }

        // Step 3: PUT bytes to LinkedIn upload URL
        try {
            $uploadResponse = Http::withToken($account->access_token)
                ->timeout(60)
                ->withBody($imageBytes, 'application/octet-stream')
                ->put($uploadUrl);
        } catch (\Throwable $e) {
            Log::error('[LinkedInPublish] image upload exception', [
                'asset_urn' => $assetUrn,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!$uploadResponse->successful()) {
            Log::warning('[LinkedInPublish] image upload PUT failed', [
                'asset_urn' => $assetUrn,
                'status' => $uploadResponse->status(),
                'body' => mb_substr((string) $uploadResponse->body(), 0, 300),
            ]);
            return null;
        }

        Log::info('[LinkedInPublish] image upload success', [
            'asset_urn' => $assetUrn,
            'source' => $sourceImageUrl,
            'bytes' => strlen($imageBytes),
        ]);

        return $assetUrn;
    }

    /**
     * Quota pause guard — returns the pause-expiry Carbon if active, null if
     * no pause is set OR if a previously set pause has elapsed (which the
     * caller may treat as "publish freely again").
     *
     * Reads Setting{key=linkedin_quota_pause_until} ISO8601 timestamp; the
     * value is written by activateQuotaPause() after a 429 response. After
     * the 24h LinkedIn daily quota window resets, the timestamp naturally
     * elapses and returns null — no manual setting cleanup required.
     */
    private function quotaPauseActiveUntil(): ?Carbon
    {
        $row = Setting::where('key', 'linkedin_quota_pause_until')->first();
        if (!$row || empty($row->value)) {
            return null;
        }
        try {
            $expiry = Carbon::parse($row->value);
        } catch (\Throwable) {
            return null;
        }
        return $expiry->isFuture() ? $expiry : null;
    }

    /**
     * Persist a 24h forward pause flag after a 429 response from LinkedIn.
     *
     * Subsequent publish() calls return early without an API call until
     * either (a) 24h elapses naturally, or (b) operator manually clears the
     * setting via tinker / admin UI. 24h matches LinkedIn's APPLICATION_AND
     * _MEMBER DAY quota reset window.
     *
     * Idempotent — if a pause is already active, this call refreshes the
     * deadline (since the 429 indicates the quota is STILL exhausted).
     *
     * @param  string  $origin  caller identifier for the log (publishText /
     *                           publishCarousel / registerUpload)
     * @param  int|null  $draftId optional draft ID for log correlation
     */
    private function activateQuotaPause(string $origin, ?int $draftId): void
    {
        $until = now()->addHours(24);
        Setting::updateOrCreate(
            ['key' => 'linkedin_quota_pause_until'],
            [
                'value' => $until->toIso8601String(),
                'type' => 'string',
                'group' => 'linkedin',
            ]
        );
        Log::warning('[LinkedInPublish] quota pause activated (LinkedIn 429)', [
            'origin' => $origin,
            'draft_id' => $draftId,
            'pause_until' => $until->toIso8601String(),
            'expected_clear' => 'auto-elapses after 24h, or operator clears Setting{key=linkedin_quota_pause_until}',
        ]);
    }
}
