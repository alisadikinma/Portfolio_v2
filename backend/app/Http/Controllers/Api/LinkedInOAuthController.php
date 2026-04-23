<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LinkedInAccount;
use App\Services\LinkedInOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * LinkedIn OAuth 2.0 authorization code flow.
 *
 *   GET  /api/admin/linkedin/connect       → redirect to LinkedIn authorize URL
 *   GET  /api/admin/linkedin/oauth/callback → exchange code, store tokens
 *   DELETE /api/admin/linkedin/account/{id} → disconnect + drop row
 *
 * The "connect" route is authenticated (admin only). The "callback"
 * route is public — LinkedIn redirects the user's browser here with a
 * code. CSRF state is verified via cached session-keyed state token.
 */
class LinkedInOAuthController extends Controller
{
    public function __construct(private readonly LinkedInOAuthService $oauth)
    {
    }

    /**
     * GET /api/admin/linkedin/connect
     *
     * Returns the LinkedIn authorize URL. Frontend opens it (window.location).
     * Backend persists a CSRF state token in session to verify on callback.
     */
    public function connect(Request $request): JsonResponse
    {
        if (empty(config('linkedin.oauth.client_id')) || empty(config('linkedin.oauth.client_secret'))) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'oauth_not_configured',
                    'message' => 'LinkedIn OAuth app credentials missing. Set LINKEDIN_OAUTH_CLIENT_ID + LINKEDIN_OAUTH_CLIENT_SECRET in .env and restart.',
                ],
            ], 503);
        }

        $state = $this->oauth->generateState();
        $request->session()->put('linkedin_oauth_state', $state);

        return response()->json([
            'success' => true,
            'data' => [
                'authorize_url' => $this->oauth->buildAuthorizeUrl($state),
                'state' => $state,
            ],
        ]);
    }

    /**
     * GET /api/admin/linkedin/oauth/callback?code=...&state=...
     *
     * LinkedIn redirects the browser here. We exchange the code for
     * tokens, fetch the profile, upsert the account, then redirect
     * the browser back to the admin settings page with a flash message.
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $errorDescription = $request->query('error_description');

        $frontendUrl = rtrim(env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')), '/');
        $settingsUrl = "{$frontendUrl}/admin/settings/about";

        if ($error !== null) {
            Log::warning('[LinkedInOAuth] callback received error', [
                'error' => $error,
                'description' => $errorDescription,
            ]);
            return redirect()->away(
                $settingsUrl . '?linkedin_oauth=error&message=' . urlencode($errorDescription ?: $error)
            );
        }

        if (empty($code) || empty($state)) {
            return redirect()->away($settingsUrl . '?linkedin_oauth=error&message=missing_code_or_state');
        }

        $expectedState = $request->session()->pull('linkedin_oauth_state');
        if (!hash_equals((string) $expectedState, (string) $state)) {
            return redirect()->away($settingsUrl . '?linkedin_oauth=error&message=state_mismatch');
        }

        try {
            $tokens = $this->oauth->exchangeCodeForTokens($code);
            $profile = $this->oauth->fetchProfile($tokens['access_token']);
            $account = $this->oauth->upsertAccount($tokens, $profile);

            return redirect()->away(
                $settingsUrl . '?linkedin_oauth=success&account=' . urlencode($account->display_name)
            );
        } catch (RuntimeException $e) {
            Log::error('[LinkedInOAuth] callback flow failed', ['message' => $e->getMessage()]);
            return redirect()->away(
                $settingsUrl . '?linkedin_oauth=error&message=' . urlencode($e->getMessage())
            );
        }
    }

    /**
     * DELETE /api/admin/linkedin/account/{id}
     *
     * Disconnect an account by deleting the row. LinkedIn API does not
     * offer a token-revocation endpoint, so we just drop local state.
     */
    public function disconnect(int $id): JsonResponse
    {
        $account = LinkedInAccount::find($id);
        if ($account === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'not_found', 'message' => 'LinkedIn account not found'],
            ], 404);
        }

        $name = $account->display_name;
        $account->delete();

        return response()->json([
            'success' => true,
            'data' => ['disconnected' => $name],
            'message' => "Disconnected {$name}",
        ]);
    }

    /**
     * GET /api/admin/linkedin/account
     *
     * Return the list of connected accounts + whether OAuth is configured.
     * Used by the Settings card to render the appropriate state.
     */
    public function index(): JsonResponse
    {
        $configured = !empty(config('linkedin.oauth.client_id'))
            && !empty(config('linkedin.oauth.client_secret'));

        $accounts = LinkedInAccount::orderByDesc('id')->get()->map(fn ($a) => [
            'id' => $a->id,
            'person_urn' => $a->person_urn,
            'display_name' => $a->display_name,
            'access_token_expires_at' => $a->access_token_expires_at?->toIso8601String(),
            'refresh_token_expires_at' => $a->refresh_token_expires_at?->toIso8601String(),
            'last_refreshed_at' => $a->last_refreshed_at?->toIso8601String(),
            'is_access_token_expired' => $a->isAccessTokenExpired(),
            'needs_refresh' => $a->needsRefresh(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'oauth_configured' => $configured,
                'accounts' => $accounts,
            ],
        ]);
    }

    /**
     * POST /api/admin/linkedin/account/{id}/test
     *
     * Ping /v2/me with the account's access_token to verify connectivity.
     */
    public function test(int $id): JsonResponse
    {
        $account = LinkedInAccount::find($id);
        if ($account === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'not_found', 'message' => 'LinkedIn account not found'],
            ], 404);
        }

        $result = $this->oauth->testConnection($account);
        return response()->json([
            'success' => $result['success'],
            'data' => $result,
        ]);
    }
}
