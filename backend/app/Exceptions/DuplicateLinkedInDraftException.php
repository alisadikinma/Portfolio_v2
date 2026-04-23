<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a regenerate attempt detects another live draft for the
 * same post_id. The regenerate flow soft-deletes the source draft +
 * creates a new pending_generation row — this exception prevents
 * creating duplicate live rows that would violate the app-level
 * "one live draft per post" invariant.
 *
 * Caught by LinkedInDraftController::regenerate and surfaced as HTTP 409.
 */
class DuplicateLinkedInDraftException extends RuntimeException
{
}
