<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiGenSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Stateless GeminiGen webhook relay.
 *
 * GeminiGen.ai only lets us register the `webhook_url` at image dispatch time —
 * it cannot inject custom HTTP headers. So both the Sanctum bearer token AND the
 * caller's actual callback URL ride in the query string:
 *
 *   POST /api/automation/geminigen/webhook
 *     ?token=<plain-text-sanctum-token-with-geminigen:relay-ability>
 *     &cb=<base64url-encoded callback URL>
 *
 * Phase D scope: auth + signature gates only. Phase E will add forwarding.
 *
 * See docs/plans/2026-05-14-geminigen-relay-webhook.md
 */
class GeminiGenRelayController extends Controller
{
    public function __construct(private GeminiGenSignatureVerifier $verifier)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // 1. Token gate (cheap path — bail before RSA verify).
        $tokenString = $request->query('token');
        if (! is_string($tokenString) || $tokenString === '') {
            return $this->error('MISSING_TOKEN', 'No relay token in query string', 401);
        }

        $accessToken = PersonalAccessToken::findToken($tokenString);
        if (! $accessToken || ! $accessToken->can('geminigen:relay')) {
            return $this->error('UNAUTHENTICATED', 'Token invalid or missing geminigen:relay ability', 401);
        }

        // Bump last_used_at for audit (matches Sanctum's own behavior for auth:sanctum routes).
        $accessToken->forceFill(['last_used_at' => now()])->save();

        // 2. Callback param gate.
        $cbParam = $request->query('cb');
        if (! is_string($cbParam) || $cbParam === '') {
            return $this->error('MISSING_CB', 'No callback URL in query string', 422);
        }

        $decoded = base64_decode(strtr($cbParam, '-_', '+/'), true);
        if ($decoded === false || ! filter_var($decoded, FILTER_VALIDATE_URL)) {
            return $this->error('INVALID_CB', 'Callback URL is not decodable or not a valid URL', 422);
        }

        // 3. Signature gate.
        $sigHeader = $request->header('X-Signature') ?? $request->header('x-signature');
        if (! is_string($sigHeader) || $sigHeader === '') {
            return $this->error('MISSING_SIGNATURE', 'No X-Signature header', 403);
        }

        $rawBody = $request->getContent();
        $publicKeyPath = config('geminigen-relay.public_key_path');
        if (! $this->verifier->verify($rawBody, $sigHeader, (string) ($publicKeyPath ?? ''))) {
            return $this->error('INVALID_SIGNATURE', 'RSA signature verification failed', 403);
        }

        // 4. Forward payload to caller. Always return 200 to GeminiGen so it doesn't
        //    retry-storm us when the caller is down; caller-down is logged for debug.
        $tokenPrefix = substr($tokenString, 0, 8);
        $timeout = (int) config('geminigen-relay.forward_timeout', 15);
        $retries = (int) config('geminigen-relay.forward_retries', 2);
        $retryDelay = (int) config('geminigen-relay.forward_retry_delay_ms', 1000);

        $startMs = (int) (microtime(true) * 1000);
        $forwardStatus = null;
        $relayed = false;
        $forwardError = null;

        try {
            $response = Http::timeout($timeout)
                ->retry($retries, $retryDelay, throw: false)
                ->withHeaders(['X-Portfolio-Relay-Token' => $tokenPrefix])
                ->withBody($rawBody, 'application/json')
                ->post($decoded);
            $forwardStatus = $response->status();
            $relayed = $response->successful();
        } catch (\Throwable $e) {
            $forwardError = $e->getMessage();
        }

        $durationMs = ((int) (microtime(true) * 1000)) - $startMs;

        Log::info('[GeminiGenRelay] forwarded', [
            'event' => $request->input('event'),
            'uuid' => $request->input('uuid'),
            'cb_host' => parse_url($decoded, PHP_URL_HOST),
            'forward_status' => $forwardStatus,
            'relayed' => $relayed,
            'duration_ms' => $durationMs,
            'error' => $forwardError,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'relayed' => $relayed,
                'forward_status' => $forwardStatus,
            ],
        ], 200);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
