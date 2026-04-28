<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LinkedInPostStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateLinkedInPost;
use App\Models\LinkedInPost;
use App\Services\LinkedInCarouselImageService;
use App\Services\LinkedInPublishService;
use App\Services\PipelineGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for LinkedIn drafts. Per plugin design §4.5 + scope pivot.
 *
 * Routes:
 *   GET    /admin/linkedin-drafts          list (filter by status, format, scope=feed|queue)
 *   GET    /admin/linkedin-drafts/{id}     show
 *   PUT    /admin/linkedin-drafts/{id}     update (saves content, does NOT transition during Phase D1)
 *   POST   /admin/linkedin-drafts/{id}/regenerate    soft-delete + create new pending_generation row
 *   POST   /admin/linkedin-drafts/{id}/approve       manual_review → awaiting_publish
 *   POST   /admin/linkedin-drafts/{id}/cancel        any non-terminal → cancelled
 *   POST   /admin/linkedin-drafts/{id}/publish-now   awaiting_publish → published (via Publisher)
 *
 * All transitions routed through PipelineGuard::advance for uniform logging.
 */
class LinkedInDraftController extends Controller
{
    public function __construct(
        private readonly PipelineGuard $guard,
        private readonly LinkedInPublishService $publisher,
        private readonly LinkedInCarouselImageService $carouselImages,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'format' => ['nullable', Rule::in(['text', 'carousel'])],
            'scope' => ['nullable', Rule::in(['feed', 'queue', 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = LinkedInPost::with(['post.translations', 'account'])
            ->orderByDesc('updated_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['format'])) {
            $query->where('format', $validated['format']);
        }
        if (($validated['scope'] ?? null) === 'feed') {
            $query->whereIn('status', LinkedInPostStatus::feedStatuses());
        } elseif (($validated['scope'] ?? null) === 'queue') {
            $query->whereIn('status', LinkedInPostStatus::queueStatuses());
        }

        $perPage = $validated['per_page'] ?? 15;
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $draft = LinkedInPost::with(['post.translations', 'account'])->find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data' => $draft,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        if (!in_array($draft->status, ['awaiting_publish', 'manual_review'], true)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'invalid_status',
                    'message' => "Cannot edit draft in status '{$draft->status}'. Allowed: awaiting_publish, manual_review.",
                ],
            ], 422);
        }

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:3000'],
            'link_comment' => ['nullable', 'string', 'max:500'],
            'hashtags' => ['nullable', 'array', 'min:3', 'max:5'],
            'hashtags.*' => ['string', 'max:60'],
            'carousel_slides' => ['nullable', 'array'],
        ]);

        $draft->update(array_filter($validated, fn ($v) => $v !== null));

        // Phase D1 stub: do NOT transition to `validating`. Plugin re-score
        // isn't wired yet — transitioning would leave the draft stuck.
        // Once plugin Phase D3 ships, a "Re-validate" button will trigger
        // status=validating + plugin dispatch.
        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'account']),
            'warnings' => ['Re-validation deferred until plugin content-generation pipeline ships'],
        ]);
    }

    public function regenerate(int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        if (in_array($draft->status, ['pending_generation', 'generating', 'validating'], true)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'regenerate_in_progress',
                    'message' => 'A generation is already in progress for this draft.',
                ],
            ], 409);
        }

        // Soft-delete current + create new row with same post_id.
        // Application-level enforcement of "one live row per post_id"
        // (see migration note about MySQL partial index limitation).
        try {
            $newDraft = DB::transaction(function () use ($draft) {
                $existingLive = LinkedInPost::where('post_id', $draft->post_id)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $draft->id)
                    ->lockForUpdate()
                    ->first();
                if ($existingLive !== null) {
                    throw new \App\Exceptions\DuplicateLinkedInDraftException(
                        "Another live draft (#{$existingLive->id}) already exists for post #{$draft->post_id}"
                    );
                }

                $draft->delete(); // soft-delete

                return LinkedInPost::create([
                    'post_id' => $draft->post_id,
                    'format' => $draft->format,
                    'content' => '',
                    'hashtags' => [],
                    'status' => LinkedInPostStatus::PendingGeneration->value,
                    'pipeline_state_log' => [[
                        'from' => 'regenerate_source',
                        'to' => 'pending_generation',
                        'reason' => 'admin_regenerate',
                        'timestamp' => now()->toIso8601String(),
                    ]],
                ]);
            });
        } catch (\App\Exceptions\DuplicateLinkedInDraftException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'duplicate_live_draft',
                    'message' => $e->getMessage(),
                ],
            ], 409);
        }

        GenerateLinkedInPost::dispatch($newDraft->id);

        Log::info('[LinkedInDraft] regenerate dispatched', [
            'old_draft_id' => $draft->id,
            'new_draft_id' => $newDraft->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $newDraft->fresh(['post.translations']),
            'message' => 'Regeneration queued. Worker will pick it up shortly.',
        ], 201);
    }

    public function approve(int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        try {
            $windowMinutes = (int) config('linkedin.cancel_window_minutes', 15);
            $this->guard->advance(
                $draft,
                LinkedInPostStatus::AwaitingPublish,
                'admin_approve',
                ['draft_id' => $draft->id]
            );

            // Set scheduling timestamps — per review fix, scheduled_at is the
            // moment the preview went out, cancel_window_ends_at is when
            // publish fires.
            $draft->update([
                'scheduled_at' => now(),
                'cancel_window_ends_at' => now()->addMinutes($windowMinutes),
            ]);

            // Self-heal: a carousel draft can land in manual_review with non-done
            // slides if the original Scenario-C dispatch in persistAndRoute
            // silently failed (logged but swallowed) or if the draft predates
            // the April 27 image-rendering shipped. Approving without rendered
            // slides would queue a publish that produces nothing, so dispatch
            // image generation here. Job is idempotent — slides already 'done'
            // are skipped.
            if ($draft->format === 'carousel') {
                $needsImages = collect($draft->carousel_slides ?? [])->contains(
                    fn ($s) => ($s['image_status'] ?? null) !== 'done' || empty($s['image_url'])
                );
                if ($needsImages) {
                    try {
                        \App\Jobs\GenerateLinkedInCarouselImages::dispatch($draft->id);
                        Log::info('[LinkedInDraft] approve auto-dispatched carousel images', [
                            'draft_id' => $draft->id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('[LinkedInDraft] approve image dispatch failed (non-fatal)', [
                            'draft_id' => $draft->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $draft->fresh(['post.translations', 'account']),
                'message' => "Approved. Publish scheduled in {$windowMinutes} minutes.",
            ]);
        } catch (InvalidStateTransitionException $e) {
            return $this->illegalTransition($e);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        try {
            $this->guard->advance(
                $draft,
                LinkedInPostStatus::Cancelled,
                'admin_cancel',
                ['draft_id' => $draft->id]
            );

            return response()->json([
                'success' => true,
                'data' => $draft->fresh(['post.translations', 'account']),
                'message' => 'Draft cancelled. Regenerate to re-attempt.',
            ]);
        } catch (InvalidStateTransitionException $e) {
            return $this->illegalTransition($e);
        }
    }

    public function publishNow(int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        // Must be in awaiting_publish (prevents double-publish + bypass of
        // the approval gate)
        if ($draft->status !== 'awaiting_publish') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'invalid_status',
                    'message' => "publish-now requires status 'awaiting_publish', got '{$draft->status}'",
                ],
            ], 422);
        }

        $result = $this->publisher->publish($draft);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'publish_failed',
                    'message' => $result['error'] ?? 'Publish failed with no error message',
                ],
                'data' => $draft,
            ], 503);
        }

        try {
            $this->guard->advance(
                $draft,
                LinkedInPostStatus::Published,
                'admin_publish_now',
                ['draft_id' => $draft->id]
            );
            $draft->update([
                'published_at' => now(),
                'linkedin_post_urn' => $result['post_urn'] ?? null,
                'linkedin_post_url' => $result['post_url'] ?? null,
            ]);
        } catch (InvalidStateTransitionException $e) {
            return $this->illegalTransition($e);
        }

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'account']),
            'message' => 'Published to LinkedIn.',
        ]);
    }

    /**
     * POST /admin/linkedin-drafts/{id}/regenerate-images
     * Re-dispatches GeminiGen for every slide that doesn't yet have a 'done'
     * image. Used when the operator wants to re-render after editing slides
     * or after a partial-failure batch.
     */
    public function regenerateAllImages(int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        if ($draft->format !== 'carousel') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'not_carousel',
                    'message' => 'Image regeneration is only valid for carousel drafts.',
                ],
            ], 422);
        }

        $slides = $draft->carousel_slides ?? [];
        $pending = collect($slides)->filter(
            fn ($s) => ($s['image_status'] ?? null) !== 'done' || empty($s['image_url'])
        )->count();

        // Optimistically mark slides 'pending' so the frontend status pills
        // flip immediately — the queue worker will move them to 'generating'
        // as it dispatches each one (~5-15s per slide for the GeminiGen
        // multipart POST handshake). Synchronous in-request dispatch was
        // hitting the axios 15s timeout on 7-10 slide carousels.
        $touched = false;
        foreach ($slides as $i => $slide) {
            if (($slide['image_status'] ?? null) !== 'done' || empty($slide['image_url'])) {
                $slides[$i]['image_status'] = 'pending';
                $slides[$i]['image_error'] = null;
                $touched = true;
            }
        }
        if ($touched) {
            $draft->update(['carousel_slides' => $slides]);
        }

        \App\Jobs\GenerateLinkedInCarouselImages::dispatch($draft->id);

        Log::info('[LinkedInDraft] regenerate-images queued', [
            'draft_id' => $draft->id,
            'pending_count' => $pending,
        ]);

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'account']),
            'message' => "Queued image regeneration for {$pending} slide(s). Webhook will populate URLs as renders complete.",
            'queued' => $pending,
        ], 202);
    }

    /**
     * POST /admin/linkedin-drafts/{id}/slides/{slideIndex}/regenerate-image
     * Re-dispatches a single slide. Used by the retry-this-slide hover button.
     * slideIndex is 0-based (matches carousel_slides array index).
     */
    public function regenerateSlideImage(int $id, int $slideIndex): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        if ($draft->format !== 'carousel') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'not_carousel',
                    'message' => 'Image regeneration is only valid for carousel drafts.',
                ],
            ], 422);
        }

        $slides = $draft->carousel_slides ?? [];
        if (! isset($slides[$slideIndex])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'slide_out_of_range',
                    'message' => "Slide index {$slideIndex} out of range (0.." . (count($slides) - 1) . ').',
                ],
            ], 422);
        }

        // If the previous failure was a GeminiGen safety refusal, the same
        // prompt will fail again. Sanitize it FIRST (strip proper nouns,
        // demote layout) so the manual retry has a real chance to succeed.
        // Idempotent — short-circuits on `image_prompt_pre_safety` sentinel.
        // Returns true only when a rewrite happened; either way we fall
        // through to dispatchSingleSlide so non-safety failures still retry
        // with the original prompt.
        $rewritten = $this->carouselImages->applySafetyRewriteIfNeeded($draft, $slideIndex);
        if ($rewritten) {
            $draft = $draft->fresh();
            Log::info('[LinkedInDraft] manual retry applied safety rewrite', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
            ]);
        }

        $uuid = $this->carouselImages->dispatchSingleSlide($draft, $slideIndex);

        if ($uuid === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'dispatch_failed',
                    'message' => 'GeminiGen dispatch returned no UUID. Check logs.',
                ],
                'data' => $draft->fresh(['post.translations', 'account']),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'account']),
            'message' => "Slide {$slideIndex} re-dispatched.",
            'job_uuid' => $uuid,
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'not_found', 'message' => 'LinkedIn draft not found'],
        ], 404);
    }

    private function illegalTransition(InvalidStateTransitionException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'illegal_transition',
                'message' => $e->getMessage(),
            ],
        ], 409);
    }
}
