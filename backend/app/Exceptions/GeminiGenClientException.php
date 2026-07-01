<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by GeminiGenClientBridge when the indusia client CLI submit fails
 * (non-zero exit, ERROR line, or no uuid parsed). Callers map this to a
 * circuit-breaker failure + null return, exactly like the old HTTP-error path.
 */
class GeminiGenClientException extends RuntimeException
{
}
