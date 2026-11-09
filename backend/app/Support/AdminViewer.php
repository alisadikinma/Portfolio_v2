<?php

namespace App\Support;

/**
 * True only for an admin session: a sanctum token carrying the wildcard ability.
 *
 * Admin login tokens are minted by AuthController with createToken() whose
 * default abilities are ['*']. Automation / cv / relay / worker tokens are minted
 * with explicit abilities (TokenController), so they never carry '*' and must NOT
 * see inactive or unpublished records. A null token (actingAs without a token)
 * is only reachable in-process, never from a real request.
 */
final class AdminViewer
{
    public static function is(): bool
    {
        $user = auth('sanctum')->user();
        if ($user === null || ! method_exists($user, 'currentAccessToken')) {
            return false;
        }
        /** @var \Laravel\Sanctum\PersonalAccessToken|\Laravel\Sanctum\TransientToken|null $token */
        $token = $user->currentAccessToken();

        return $token === null || $token->can('*');
    }
}
