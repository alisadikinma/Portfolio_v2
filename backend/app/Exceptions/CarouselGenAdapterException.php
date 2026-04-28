<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CarouselGenOutputAdapter when the /carousel-gen plugin's stdout
 * JSON cannot be mapped onto the linkedin_posts.carousel_slides shape.
 *
 * Two trigger conditions:
 *   1. Input envelope has status=failed (plugin reported its own failure;
 *      the exception message surfaces the plugin's `error` string).
 *   2. Input envelope has status=<unknown> (neither 'complete' nor 'failed';
 *      schema drift on the plugin side that the adapter refuses to silently
 *      coerce).
 *
 * Caught by LinkedInGenerationService::routeCarouselGeneration (Phase A6) and
 * surfaced via PipelineGuard::advance to the LinkedInPostStatus::Failed FSM
 * state with reason='carousel_gen_failed'.
 */
class CarouselGenAdapterException extends RuntimeException
{
}
