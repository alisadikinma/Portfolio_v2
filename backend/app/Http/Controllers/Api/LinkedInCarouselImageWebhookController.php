<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LinkedInCarouselImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public webhook endpoint that GeminiGen calls when a LinkedIn carousel slide
 * image-generation job completes or fails.
 *
 * Kept separate from BlogPipelineController::imageWebhook because:
 *   - LinkedIn carousel jobs route through LinkedInCarouselImageService
 *     (mirrors slide status onto LinkedInPost.carousel_slides JSON column)
 *   - Blog/article jobs route through ImageGenerationService
 *     (mirrors onto Post.featured_image / inline <figure> + ContentIdea)
 *
 * Both endpoints accept the same GeminiGen payload shape ({ event, uuid, data })
 * — the differentiator is the URL path.
 */
class LinkedInCarouselImageWebhookController extends Controller
{
    public function __construct(
        private readonly LinkedInCarouselImageService $service,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $event = (string) $request->input('event', '');
        $uuid = (string) $request->input('uuid', '');
        $data = (array) $request->input('data', []);

        Log::info('[LinkedInCarouselImageWebhook] received', [
            'event' => $event,
            'uuid' => $uuid,
        ]);

        if ($uuid === '' || $event === '') {
            return response()->json([
                'success' => false,
                'error' => 'Missing event or uuid',
            ], 400);
        }

        $success = $this->service->handleWebhook($uuid, $event, $data);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Slide image processed' : 'Failed to process slide image',
        ]);
    }
}
