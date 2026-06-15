<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised on a non-recoverable Zernio API failure (missing key, 401/403, or an
 * unexpected response shape). Transient failures (5xx / network) are left to
 * Laravel's HTTP retry() + the queue job's retry — they do NOT throw this.
 *
 * HTTP 409 (content-dedup) is NOT an error here: createPost() returns a
 * {duplicate:true, existingPostId} result instead so the publish job can treat
 * it as idempotent success.
 */
class ZernioApiException extends RuntimeException
{
}
