<?php

namespace App\Services;

use App\Jobs\PostLinkedInFirstComment;
use App\Models\LinkedInAccount;
use App\Models\LinkedInPost;
use Illuminate\Http\Client\Response;
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

        $author = "urn:li:person:{$account->person_urn}";

        $payload = [
            'author' => $author,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $body],
                    'shareMediaCategory' => 'NONE',
                ],
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
                    'LinkedIn-Version' => (string) config('linkedin.api.version', '202405'),
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
     * Publish a carousel PDF via 3-step flow. DEFERRED — requires PDF
     * composition via TCPDF (plugin Phase D11). For now returns 503.
     */
    public function publishCarousel(LinkedInPost $draft, LinkedInAccount $account): array
    {
        Log::info('[LinkedInPublish] publishCarousel invoked (deferred)', [
            'draft_id' => $draft->id,
            'account_id' => $account->id,
            'slide_count' => is_array($draft->carousel_slides) ? count($draft->carousel_slides) : 0,
        ]);

        return [
            'success' => false,
            'post_urn' => null,
            'post_url' => null,
            'error' => 'Carousel publish deferred — PDF composition (TCPDF) + document upload pending. Route to manual_review for now.',
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
            'actor' => "urn:li:person:{$account->person_urn}",
            'object' => $postUrn,
            'message' => ['text' => $linkComment],
        ];

        try {
            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type' => 'application/json',
                    'LinkedIn-Version' => (string) config('linkedin.api.version', '202405'),
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
}
