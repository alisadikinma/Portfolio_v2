<?php

namespace App\Services;

use App\Models\LinkedInAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Handles the LinkedIn OAuth 2.0 authorization code flow:
 *   1. buildAuthorizeUrl() — generate redirect URL with CSRF state
 *   2. exchangeCodeForTokens() — POST to /oauth/v2/accessToken with the
 *      code returned in the callback, get access_token + refresh_token
 *   3. fetchProfile() — GET /v2/me to extract person URN + display name
 *   4. upsertAccount() — write (or update) LinkedInAccount row
 *
 * All network calls use Laravel HTTP client with 10-second timeout.
 * All errors are logged + rethrown so the controller can render
 * human-readable error pages.
 */
class LinkedInOAuthService
{
    public function buildAuthorizeUrl(string $state): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => config('linkedin.oauth.client_id'),
            'redirect_uri' => config('linkedin.oauth.redirect_uri'),
            'state' => $state,
            'scope' => config('linkedin.oauth.scopes'),
        ]);

        return config('linkedin.oauth.authorize_url') . '?' . $params;
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    /**
     * Exchange authorization code for tokens.
     * Returns the decoded token response so the caller can merge
     * with /v2/me profile data before upserting the account row.
     *
     * @throws RuntimeException on LinkedIn API error
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $response = Http::asForm()->timeout(10)->post(
            config('linkedin.oauth.token_url'),
            [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('linkedin.oauth.redirect_uri'),
                'client_id' => config('linkedin.oauth.client_id'),
                'client_secret' => config('linkedin.oauth.client_secret'),
            ]
        );

        if ($response->failed()) {
            Log::error('[LinkedInOAuth] token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException(
                'LinkedIn token exchange failed: ' . $response->json('error_description', 'unknown error')
            );
        }

        return $response->json();
    }

    /**
     * Fetch /v2/me to extract person URN (id) + display name.
     *
     * @throws RuntimeException on profile fetch failure
     */
    public function fetchProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->withHeaders(['LinkedIn-Version' => config('linkedin.api.version')])
            ->timeout(10)
            ->get(config('linkedin.api.base_url') . config('linkedin.api.me_endpoint'));

        if ($response->failed()) {
            Log::error('[LinkedInOAuth] /v2/me fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException(
                'LinkedIn profile fetch failed: HTTP ' . $response->status()
            );
        }

        $data = $response->json();
        $personId = $data['id'] ?? null;
        $firstName = $data['localizedFirstName'] ?? $data['firstName']['localized']['en_US'] ?? '';
        $lastName = $data['localizedLastName'] ?? $data['lastName']['localized']['en_US'] ?? '';

        if (empty($personId)) {
            throw new RuntimeException('LinkedIn /v2/me returned no id field');
        }

        return [
            'person_urn' => 'urn:li:person:' . $personId,
            'display_name' => trim("{$firstName} {$lastName}") ?: 'LinkedIn User',
        ];
    }

    /**
     * Upsert a LinkedInAccount row from token + profile data.
     * Matched on person_urn unique constraint — re-connecting the same
     * account refreshes tokens without creating duplicate rows.
     */
    public function upsertAccount(array $tokens, array $profile): LinkedInAccount
    {
        $expiresIn = (int) ($tokens['expires_in'] ?? 5184000); // default 60 days
        $refreshExpiresIn = (int) ($tokens['refresh_token_expires_in'] ?? 31536000); // default 365 days

        return LinkedInAccount::updateOrCreate(
            ['person_urn' => $profile['person_urn']],
            [
                'display_name' => $profile['display_name'],
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? '',
                'access_token_expires_at' => now()->addSeconds($expiresIn),
                'refresh_token_expires_at' => now()->addSeconds($refreshExpiresIn),
                'last_refreshed_at' => now(),
            ]
        );
    }

    /**
     * Refresh an account's access_token using its refresh_token.
     *
     * @throws RuntimeException on refresh failure
     */
    public function refreshAccount(LinkedInAccount $account): LinkedInAccount
    {
        if ($account->isRefreshTokenExpired()) {
            throw new RuntimeException(
                "LinkedIn refresh_token expired for account #{$account->id} — user must re-authorize"
            );
        }

        $response = Http::asForm()->timeout(10)->post(
            config('linkedin.oauth.token_url'),
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
                'client_id' => config('linkedin.oauth.client_id'),
                'client_secret' => config('linkedin.oauth.client_secret'),
            ]
        );

        if ($response->failed()) {
            Log::error('[LinkedInOAuth] refresh failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
            ]);
            throw new RuntimeException(
                'LinkedIn token refresh failed: ' . $response->json('error_description', 'unknown error')
            );
        }

        $tokens = $response->json();
        $expiresIn = (int) ($tokens['expires_in'] ?? 5184000);

        $account->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
            'access_token_expires_at' => now()->addSeconds($expiresIn),
            'last_refreshed_at' => now(),
        ]);

        return $account->fresh();
    }

    /**
     * Lightweight connectivity check — hits /v2/me to verify the
     * account's access_token still works.
     *
     * Returns: ['success' => bool, 'message' => string]
     */
    public function testConnection(LinkedInAccount $account): array
    {
        if ($account->isAccessTokenExpired()) {
            return [
                'success' => false,
                'message' => 'Access token expired — refresh or reconnect',
            ];
        }

        try {
            $this->fetchProfile($account->access_token);
            return [
                'success' => true,
                'message' => 'Connected as ' . $account->display_name,
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
