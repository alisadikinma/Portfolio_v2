<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiGenSignatureVerifier
{
    /**
     * Verify a GeminiGen webhook x-signature header against the configured public key.
     *
     * Algorithm per GeminiGen docs (https://docs.geminigen.ai/getting-started/webhooks):
     *   md5_raw = md5(body, raw=true)
     *   openssl_verify(md5_raw, hex2bin(signature), public_key, SHA256)
     *
     * @param  string  $body              raw request body
     * @param  string  $signatureHex      hex-encoded value of the x-signature header
     * @param  string  $publicKeyPath     filesystem path to the PEM public key
     * @return bool    true iff signature is valid; false on any failure (file missing, bad hex, mismatch, exception)
     */
    public function verify(string $body, string $signatureHex, string $publicKeyPath): bool
    {
        try {
            if (! is_readable($publicKeyPath)) {
                Log::warning('[GeminiGenRelay] public key file not readable', ['path' => $publicKeyPath]);
                return false;
            }

            $sigBytes = @hex2bin($signatureHex);
            if ($sigBytes === false) {
                return false;
            }

            $publicKey = @openssl_pkey_get_public(file_get_contents($publicKeyPath));
            if ($publicKey === false) {
                Log::warning('[GeminiGenRelay] could not parse public key', ['path' => $publicKeyPath]);
                return false;
            }

            $md5Raw = md5($body, true);
            $result = openssl_verify($md5Raw, $sigBytes, $publicKey, OPENSSL_ALGO_SHA256);

            return $result === 1;
        } catch (Throwable $e) {
            Log::warning('[GeminiGenRelay] signature verify exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
