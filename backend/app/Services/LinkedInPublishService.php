<?php

namespace App\Services;

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
 *
 * This milestone SCAFFOLDS the service with full method signatures + an
 * `enabled` flag so callers can invoke it without crashing. Actual API
 * integration ships once the plugin pipeline (content generation) is
 * wired end-to-end — until then, publish methods return a structured
 * "not yet ready" error so the admin UI renders a clear state.
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
     */
    public function publishText(LinkedInPost $draft, LinkedInAccount $account): array
    {
        // Scaffold stub — actual UGC post payload + API call deferred until plugin
        // integration complete. Logged to audit trail so smoke tests can verify
        // the service was reached.
        Log::info('[LinkedInPublish] publishText stub invoked', [
            'draft_id' => $draft->id,
            'account_id' => $account->id,
            'format' => 'text',
        ]);

        return [
            'success' => false,
            'post_urn' => null,
            'post_url' => null,
            'error' => 'LinkedIn publish (text) not yet wired — plugin content generation must ship first. See plugin Addendum 3 §13.2.',
        ];
    }

    /**
     * Publish a carousel PDF via 3-step flow:
     *   1. POST /v2/assets?action=registerUpload → returns upload URL + asset URN
     *   2. PUT PDF bytes to upload URL
     *   3. POST /v2/ugcPosts with shareMediaCategory: DOCUMENT + asset URN
     */
    public function publishCarousel(LinkedInPost $draft, LinkedInAccount $account): array
    {
        Log::info('[LinkedInPublish] publishCarousel stub invoked', [
            'draft_id' => $draft->id,
            'account_id' => $account->id,
            'slide_count' => is_array($draft->carousel_slides) ? count($draft->carousel_slides) : 0,
        ]);

        return [
            'success' => false,
            'post_urn' => null,
            'post_url' => null,
            'error' => 'LinkedIn publish (carousel) not yet wired — PDF composition + document upload pending plugin Phase D11. See plugin Addendum 3 §13.2.',
        ];
    }

    /**
     * Post a first-comment with the blog link on a published UGC post.
     * Stub for now — actual POST /v2/socialActions/{urn}/comments
     * ships alongside publishText.
     */
    public function postFirstComment(string $postUrn, string $linkComment, LinkedInAccount $account): array
    {
        Log::info('[LinkedInPublish] postFirstComment stub invoked', [
            'post_urn' => $postUrn,
            'account_id' => $account->id,
        ]);

        return [
            'success' => false,
            'error' => 'First-comment automation not yet wired — ships with publishText',
        ];
    }
}
