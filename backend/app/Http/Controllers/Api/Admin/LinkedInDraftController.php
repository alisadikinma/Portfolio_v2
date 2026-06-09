<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LinkedInPostStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateLinkedInPost;
use App\Models\LinkedInPost;
use App\Services\LinkedInCarouselImageService;
use App\Services\LinkedInGenerationService;
use App\Services\LinkedInPublishService;
use App\Services\LinkedInScheduleConflictService;
use App\Services\PipelineGuard;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        private readonly TelegramNotificationService $telegram,
        private readonly LinkedInGenerationService $generation,
        private readonly LinkedInScheduleConflictService $conflictService,
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

        // List endpoint over-fetched in two ways before this slim:
        //   1. post.translations pulled the full HTML article body (~30KB/row × 2
        //      languages) that the list UI never renders — it only needs title.
        //   2. carousel_slides JSON shipped the plugin-authored image_prompt
        //      (~1-2KB/slide × 9 slides/row) which the list also never renders.
        // Per-row dropped: ~50-70KB. With per_page=100 in LinkedInQueueList.vue,
        // that compounded into a ~5-10MB cold-load payload + identical body
        // every 30s refetch (ETag md5'd over the full string before 304-ing).
        // show() keeps the full payload (single row, detail page needs body).
        $query = LinkedInPost::with([
                'post' => fn ($q) => $q->select('id', 'slug', 'featured_image', 'published_at'),
                'post.translations' => fn ($q) => $q->select('id', 'post_id', 'language', 'title'),
                'post.contentIdea:id,result_post_id,virality_score',
                'account',
            ])
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

        // Strip image_prompt + image_prompt_pre_safety from carousel_slides for
        // list responses. The list UI consumes only image_status/image_url to
        // render thumbnails and status badges. Detail page goes through show()
        // which keeps the full slide payload.
        $items = array_map(function ($draft) {
            if ($draft->format === 'carousel' && is_array($draft->carousel_slides)) {
                $draft->carousel_slides = array_map(
                    fn ($slide) => is_array($slide)
                        ? array_diff_key($slide, array_flip(['image_prompt', 'image_prompt_pre_safety']))
                        : $slide,
                    $draft->carousel_slides
                );
            }
            return $draft;
        }, $paginator->items());

        return response()->json([
            'success' => true,
            'data' => $items,
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
        $draft = LinkedInPost::with([
            'post.translations',
            'post.contentIdea:id,result_post_id,virality_score',
            'account',
            // Cross-post siblings so the detail page can render in-place
            // platform tabs (LinkedIn / FB / IG / TikTok) that swap caption
            // + hashtags + status without navigating away.
            // facebook_posts intentionally has NO link_comment column (FB
            // hidden from UI per May 10 cleanup; revive when direct FB
            // Graph API integration ships — add migration + re-add field).
            'facebookPost:id,linkedin_post_id,status,caption,hashtags,scheduled_at,published_at,external_url',
            'instagramPost:id,linkedin_post_id,status,title,caption,hashtags,link_comment,scheduled_at,published_at,external_url',
            // tiktok title is REQUIRED by Publer for photo carousel posts
            // (max 90 chars per Publer API spec). Plugin emits it; surface
            // to UI so operator sees what'll ship to TikTok.
            'tiktokPost:id,linkedin_post_id,status,title,caption,hashtags,link_comment,scheduled_at,published_at,external_url',
            'threadsPost:id,linkedin_post_id,status,title,caption,hashtags,link_comment,scheduled_at,published_at,external_url',
        ])->find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data' => $draft,
        ]);
    }

    /**
     * Streaming progress for an in-flight draft. Mirrors the ContentIdea
     * progress endpoint shape so the LinkedInDraftDetail page can reuse
     * the same modal pattern (phase cards + step pills + log viewer).
     *
     * Two distinct liveness signals:
     *   process_alive=true while status ∈ {pending_generation, generating,
     *     validating} — i.e. the BRIEF→CAROUSEL_GEN→VALIDATE pipeline is
     *     actively running. Frontend uses this to render the phase tracker.
     *   images_pending=true when ANY carousel slide has image_status ∈
     *     {pending, generating} — i.e. only image rendering is in flight.
     *     Frontend treats this differently because the slide thumbnail strip
     *     already provides per-slide visibility; mirroring it onto the
     *     phase tracker would falsely highlight BRIEF/SSH Dispatch as
     *     "active" when nothing is actually running on Sonnet/SSH.
     */
    public function progress(int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        $inFlightStatuses = ['pending_generation', 'generating', 'validating'];
        $processAlive = in_array($draft->status, $inFlightStatuses, true);

        $imagesPending = false;
        if ($draft->format === 'carousel' && is_array($draft->carousel_slides)) {
            foreach ($draft->carousel_slides as $slide) {
                $st = $slide['image_status'] ?? null;
                if ($st === 'pending' || $st === 'generating') {
                    $imagesPending = true;
                    break;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $draft->id,
                'status' => $draft->status,
                'format' => $draft->format,
                'progress_percentage' => (int) ($draft->progress_percentage ?? 0),
                'current_step' => $draft->current_step,
                'progress_log' => $draft->progress_log ?? [],
                'process_alive' => $processAlive,
                'images_pending' => $imagesPending,
                'last_error' => $draft->last_error,
            ],
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
            'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
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

    /**
     * POST /admin/linkedin-drafts/{id}/generate-threads
     *
     * One-off Threads cross-post sibling creation for an existing LinkedIn
     * draft that doesn't yet have one. Used when the LinkedIn draft pre-dates
     * the /threads-gen plugin (May 10, 2026 ship). Idempotent — returns 409 if
     * a Threads sibling already exists.
     *
     * Mirrors `ScanLinkedInForCrossPost::createThreads()` for one specific
     * LinkedIn draft. The bulk equivalent is the
     * `linkedin:backfill-threads` artisan command.
     */
    public function generateThreads(int $id): JsonResponse
    {
        $draft = LinkedInPost::with('threadsPost')->find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        if ($draft->threadsPost !== null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'threads_sibling_exists',
                    'message' => "Threads draft #{$draft->threadsPost->id} already exists for this LinkedIn post.",
                ],
            ], 409);
        }

        $format = $draft->format ?? 'text';

        $threadsDraft = \App\Models\ThreadsPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'format' => $format,
            'status' => \App\Enums\ThreadsPostStatus::PendingGeneration->value,
            'pipeline_state_log' => [[
                'from' => 'admin_one_off',
                'to' => 'pending_generation',
                'reason' => 'admin_generate_threads_button',
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);

        \App\Jobs\GenerateThreadsPost::dispatch($threadsDraft->id);

        Log::info('[LinkedInDraft] Threads sibling created via admin button', [
            'linkedin_post_id' => $draft->id,
            'threads_post_id' => $threadsDraft->id,
            'format' => $format,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['threads_post_id' => $threadsDraft->id, 'format' => $format],
            'message' => 'Threads draft created. Worker will generate caption shortly.',
        ], 201);
    }

    /**
     * Re-generate the cross-post sibling for a single platform without touching
     * the LinkedIn parent. Resets the sibling's caption/hashtags/link_comment
     * + flips status to pending_generation, then re-dispatches the platform's
     * Generate*Post job. Used by the per-platform "Regenerate" buttons in
     * LinkedInDraftDetail.vue's platform tab strip.
     *
     * Idempotent — refuses to fire while sibling is mid-pipeline (409).
     */
    public function regenerateInstagram(int $id): JsonResponse
    {
        return $this->regenerateCrossPost(
            id: $id,
            relation: 'instagramPost',
            platformLabel: 'Instagram',
            jobClass: \App\Jobs\GenerateInstagramPost::class,
            statusEnum: \App\Enums\InstagramPostStatus::class,
            extraResetFields: ['text_only_caption' => null],
        );
    }

    public function regenerateTiktok(int $id): JsonResponse
    {
        return $this->regenerateCrossPost(
            id: $id,
            relation: 'tiktokPost',
            platformLabel: 'TikTok',
            jobClass: \App\Jobs\GenerateTiktokPost::class,
            statusEnum: \App\Enums\TiktokPostStatus::class,
        );
    }

    public function regenerateThreads(int $id): JsonResponse
    {
        return $this->regenerateCrossPost(
            id: $id,
            relation: 'threadsPost',
            platformLabel: 'Threads',
            jobClass: \App\Jobs\GenerateThreadsPost::class,
            statusEnum: \App\Enums\ThreadsPostStatus::class,
        );
    }

    /**
     * Per-platform JsonResponse wrapper around dispatchSiblingRegen.
     */
    private function regenerateCrossPost(
        int $id,
        string $relation,
        string $platformLabel,
        string $jobClass,
        string $statusEnum,
        array $extraResetFields = []
    ): JsonResponse {
        $draft = LinkedInPost::with($relation)->find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        $result = $this->dispatchSiblingRegen($draft, $relation, $platformLabel, $jobClass, $statusEnum, $extraResetFields);

        if ($result['outcome'] === 'missing') {
            return response()->json(['success' => false, 'error' => ['code' => 'sibling_missing', 'message' => $result['message']]], 404);
        }
        if ($result['outcome'] === 'in_progress') {
            return response()->json(['success' => false, 'error' => ['code' => 'regenerate_in_progress', 'message' => $result['message']]], 409);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sibling_id' => $result['sibling_id'],
                'previous_status' => $result['previous_status'],
                'new_status' => $statusEnum::PendingGeneration->value,
            ],
            'message' => "{$platformLabel} draft reset + dispatched. Worker will regenerate caption shortly (~30s).",
        ]);
    }

    /**
     * Shared cross-post regen core. Returns array with outcome + diagnostics
     * so both single-platform endpoints AND regenerateAllCaptions can reuse.
     *
     * Outcomes: 'dispatched' | 'missing' | 'in_progress'
     */
    private function dispatchSiblingRegen(
        LinkedInPost $draft,
        string $relation,
        string $platformLabel,
        string $jobClass,
        string $statusEnum,
        array $extraResetFields = []
    ): array {
        $sibling = $draft->{$relation};
        if ($sibling === null) {
            return [
                'outcome' => 'missing',
                'message' => "{$platformLabel} sibling does not exist.",
            ];
        }

        // Only block when worker is ACTIVELY processing (generating/validating).
        // pending_generation can be a real queued job OR an orphan (status set
        // but no job in queue — happens when worker crashes mid-dispatch or
        // queue table gets manually pruned). Re-dispatching pending_generation
        // is safe: Laravel queue will execute the job; if a duplicate exists,
        // both runs hit the same FSM transition path which is idempotent.
        $activelyRunning = ['generating', 'validating'];
        if (in_array($sibling->status, $activelyRunning, true)) {
            return [
                'outcome' => 'in_progress',
                'message' => "{$platformLabel} actively running (status={$sibling->status}).",
                'sibling_id' => $sibling->id,
            ];
        }

        $previousStatus = $sibling->status;
        $resetFields = array_merge([
            'status' => $statusEnum::PendingGeneration->value,
            'caption' => null,
            'link_comment' => null,
            'hashtags' => [],
            'last_error' => null,
        ], $extraResetFields);

        $sibling->update($resetFields);

        $log = is_array($sibling->pipeline_state_log) ? $sibling->pipeline_state_log : [];
        $log[] = [
            'from' => $previousStatus,
            'to' => $statusEnum::PendingGeneration->value,
            'reason' => 'admin_regenerate_button',
            'timestamp' => now()->toIso8601String(),
        ];
        $sibling->update(['pipeline_state_log' => array_slice($log, -20)]);

        $jobClass::dispatch($sibling->id);

        Log::info("[LinkedInDraft] {$platformLabel} sibling regen dispatched", [
            'linkedin_post_id' => $draft->id,
            'sibling_id' => $sibling->id,
            'previous_status' => $previousStatus,
        ]);

        return [
            'outcome' => 'dispatched',
            'sibling_id' => $sibling->id,
            'previous_status' => $previousStatus,
        ];
    }

    /**
     * Unified "regenerate ALL platform captions" — single button that fans
     * out across LinkedIn (carousel sync OR text full-pipeline) + IG + TT
     * + Threads. Per-platform outcomes returned so frontend can render a
     * granular toast: "LinkedIn ✓ refreshed · Instagram → dispatched ·
     * TikTok skipped (mid-pipeline) · Threads — no sibling".
     *
     * LinkedIn behavior:
     *   - format=carousel → sync regenerateCaption (~1s, slides untouched)
     *   - format=text     → flips to pending_generation + dispatches full
     *                       GenerateLinkedInPost (~30-90s)
     *
     * Cross-post siblings reuse dispatchSiblingRegen (~30s each, async).
     * Missing siblings reported but never created (operator must explicitly
     * use Generate Threads / wait for cron to create IG/TT).
     */
    public function regenerateAllCaptions(int $id, LinkedInGenerationService $linkedInGen): JsonResponse
    {
        $draft = LinkedInPost::with(['post.translations', 'instagramPost', 'tiktokPost', 'threadsPost'])->find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        $results = [];

        // LinkedIn — branch by format
        if ($draft->format === 'carousel') {
            $captionResult = $linkedInGen->regenerateCaption($draft);
            $results['linkedin'] = $captionResult['success']
                ? ['outcome' => 'refreshed', 'mode' => 'sync', 'message' => 'LinkedIn caption refreshed (sync)']
                : ['outcome' => 'failed', 'mode' => 'sync', 'message' => $captionResult['error'] ?? 'Caption regen failed'];
        } else {
            // text format — full pipeline (post body IS the caption).
            // Only block actively-running statuses; pending_generation may be
            // an orphan (job dropped from queue) so allow re-dispatch.
            $linkedInRunning = ['generating', 'validating'];
            if (in_array($draft->status, $linkedInRunning, true)) {
                $results['linkedin'] = ['outcome' => 'in_progress', 'mode' => 'async', 'message' => "LinkedIn actively running (status={$draft->status})"];
            } else {
                $previousStatus = $draft->status;
                $draft->update([
                    'status' => LinkedInPostStatus::PendingGeneration->value,
                    'content' => '',
                    'link_comment' => null,
                    'hashtags' => [],
                    'last_error' => null,
                ]);
                $log = is_array($draft->pipeline_state_log) ? $draft->pipeline_state_log : [];
                $log[] = [
                    'from' => $previousStatus,
                    'to' => LinkedInPostStatus::PendingGeneration->value,
                    'reason' => 'admin_regenerate_all_captions',
                    'timestamp' => now()->toIso8601String(),
                ];
                $draft->update(['pipeline_state_log' => array_slice($log, -20)]);
                GenerateLinkedInPost::dispatch($draft->id);
                $results['linkedin'] = ['outcome' => 'dispatched', 'mode' => 'async', 'message' => 'LinkedIn text reset + dispatched (full pipeline ~30-90s)'];
            }
        }

        // Cross-post siblings — fan out via shared helper
        $platforms = [
            'instagram' => ['relation' => 'instagramPost', 'label' => 'Instagram', 'job' => \App\Jobs\GenerateInstagramPost::class, 'enum' => \App\Enums\InstagramPostStatus::class, 'extra' => ['text_only_caption' => null]],
            'tiktok' => ['relation' => 'tiktokPost', 'label' => 'TikTok', 'job' => \App\Jobs\GenerateTiktokPost::class, 'enum' => \App\Enums\TiktokPostStatus::class, 'extra' => []],
            'threads' => ['relation' => 'threadsPost', 'label' => 'Threads', 'job' => \App\Jobs\GenerateThreadsPost::class, 'enum' => \App\Enums\ThreadsPostStatus::class, 'extra' => []],
        ];

        foreach ($platforms as $key => $cfg) {
            $r = $this->dispatchSiblingRegen($draft, $cfg['relation'], $cfg['label'], $cfg['job'], $cfg['enum'], $cfg['extra']);
            $results[$key] = match ($r['outcome']) {
                'dispatched' => ['outcome' => 'dispatched', 'mode' => 'async', 'message' => "{$cfg['label']} reset + dispatched (~30s)"],
                'in_progress' => ['outcome' => 'in_progress', 'mode' => 'async', 'message' => $r['message']],
                'missing' => ['outcome' => 'missing', 'mode' => 'none', 'message' => "{$cfg['label']} sibling does not exist — skipped"],
                default => ['outcome' => 'unknown', 'mode' => 'none', 'message' => 'unknown outcome'],
            };
        }

        Log::info('[LinkedInDraft] Unified regenerate-all-captions fan-out', [
            'linkedin_post_id' => $draft->id,
            'results' => $results,
        ]);

        // Build human summary for toast
        $summaryParts = [];
        foreach ($results as $platform => $r) {
            $icon = match ($r['outcome']) {
                'refreshed' => '✓',
                'dispatched' => '→',
                'in_progress' => '⟳',
                'missing' => '—',
                'failed' => '✕',
                default => '?',
            };
            $summaryParts[] = "{$icon} " . ucfirst($platform);
        }
        $summary = implode(' · ', $summaryParts);

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => "Captions regen fan-out: {$summary}",
        ]);
    }

    public function approve(int $id, Request $request): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        // Optional `publish_at` lets the operator pick an exact future
        // datetime for the post to fire, instead of using the default
        // cancel-window. Useful for scheduling posts at peak engagement
        // hours (Tuesday 9am, etc.). When omitted, fall back to the
        // legacy "now + cancel_window_minutes" behaviour.
        $validated = $request->validate([
            'publish_at' => ['nullable', 'date', 'after:now'],
        ]);

        // Server-side carousel readiness gate. UI disables the approve
        // button when slides aren't all rendered (carouselReadyForApprove
        // in LinkedInQueueList.vue), but a curl/script bypass should hit
        // the same wall — defense-in-depth. Skipping this check let auto-
        // schedule cron + manual approve push half-rendered carousels into
        // awaiting_publish, where the publish cron would either ship a
        // broken post or fail and waste the slot.
        if ($draft->format === 'carousel'
            && $draft->status !== LinkedInPostStatus::AwaitingPublish->value) {
            $slides = $draft->carousel_slides ?? [];
            $notReady = empty($slides) || collect($slides)->contains(
                fn ($s) => ($s['image_status'] ?? null) !== 'done' || empty($s['image_url'])
            );
            if ($notReady) {
                // Self-heal: kick image gen now so the operator can come
                // back and try again once slides finish. Idempotent.
                try {
                    \App\Jobs\GenerateLinkedInCarouselImages::dispatch($draft->id);
                } catch (\Throwable $e) {
                    Log::warning('[LinkedInDraft] approve gate dispatch failed (non-fatal)', [
                        'draft_id' => $draft->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $done = collect($slides)->filter(
                    fn ($s) => ($s['image_status'] ?? null) === 'done' && ! empty($s['image_url'])
                )->count();
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'carousel_not_ready',
                        'message' => sprintf(
                            'Cannot approve: only %d of %d slides rendered. Image regeneration has been re-dispatched — try again once all slides show "done".',
                            $done,
                            count($slides)
                        ),
                    ],
                ], 409);
            }
        }

        try {
            // Fixed-slot scheduling (May 12, 2026): when operator doesn't
            // specify publish_at, assign the next available slot from
            // linkedin_publish_slots (default [5,6,7,12,17,18,19,20] WIB).
            // 1-post-per-slot FIFO. Replaces legacy now+15min cancel_window
            // behaviour so posts fire at predictable hours.
            //
            // Operator-provided publish_at still honoured (manual override).
            if (! empty($validated['publish_at'])) {
                $publishAt = \Carbon\Carbon::parse($validated['publish_at']);
            } else {
                $publishAt = app(\App\Services\LinkedInFixedSlotScheduler::class)
                    ->nextAvailableSlot();
            }

            // For drafts already in awaiting_publish, this endpoint becomes
            // a "reschedule" — only update timestamps without an FSM
            // transition. PipelineGuard would reject same-state transitions.
            if ($draft->status !== LinkedInPostStatus::AwaitingPublish->value) {
                $this->guard->advance(
                    $draft,
                    LinkedInPostStatus::AwaitingPublish,
                    'admin_approve',
                    ['draft_id' => $draft->id, 'publish_at' => $publishAt->toIso8601String()]
                );
            }

            // Post-May-12: scheduled_at AND cancel_window_ends_at both =
            // the slot time. Operator can cancel anytime between approve
            // click and slot fire (could be hours). LinkedIn process-
            // scheduled cron fires when cancel_window_ends_at <= now()
            // — same trigger logic, just at predictable slot times.
            $draft->update([
                'scheduled_at' => $publishAt,
                'cancel_window_ends_at' => $publishAt,
            ]);

            // P5 (May 12): propagate slot to cross-post siblings so the
            // social:publish-slot atomic orchestrator finds them at the same
            // minute tick. Scanner's 2-min cron handles drafts whose siblings
            // don't exist yet at approve time.
            $draft->load(['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost']);
            foreach (['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'] as $rel) {
                $draft->$rel?->update(['scheduled_at' => $publishAt]);
            }

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

            // Auto fan-out cross-posts at the same scheduled timing.
            // Per operator request May 10: reschedule should also push the
            // schedule to Publer so cross-posts publish at the SAME future
            // time as LinkedIn. Sets cascade flag + triggers
            // social-cross-post:scan targeted to this draft only — bypasses
            // time + virality gates since operator explicitly approved.
            // Publer scheduled-publish API support is on the
            // PublishViaPubler real-impl roadmap (currently STUB —
            // cross-post drafts get created + queued, but actual Publer
            // shipment with the scheduled_at timestamp lands when the
            // PublishViaPubler real impl ships per CLAUDE.md note).
            $draft->update(['auto_approve_cross_posts' => true]);
            try {
                \Illuminate\Support\Facades\Artisan::queue('social-cross-post:scan', [
                    '--draft-id' => $draft->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[LinkedInDraft] approve cross-post fan-out dispatch failed (non-fatal)', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
                'message' => 'Scheduled for ' . $publishAt->toIso8601String() . ' — cross-post fan-out queued to Publer.',
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
                'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
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

        try {
            $this->telegram->sendLinkedInPublishSuccess($draft->fresh(['post.translations']));
        } catch (\Throwable $e) {
            Log::warning('[LinkedInDraftController] Telegram publish notification threw', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Auto fan-out cross-posts (Publer publish for IG/TT/Threads/FB).
        // Per operator request May 10: "Publish now" should publish to ALL
        // platforms, not just LinkedIn. Sets the cascade flag + triggers
        // social-cross-post:scan targeted to this draft only — bypasses
        // time/virality gates since operator already opted in.
        $draft->update(['auto_approve_cross_posts' => true]);
        try {
            \Illuminate\Support\Facades\Artisan::queue('social-cross-post:scan', [
                '--draft-id' => $draft->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[LinkedInDraftController] Cross-post fan-out dispatch failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Directly publish any sibling whose caption is ALREADY generated.
        // The scan above only (re)dispatches caption-gen for MISSING /
        // regenerating siblings, and the auto-publish hook
        // (BaseSocialGenerationService) only fires on caption-gen COMPLETION —
        // so a sibling already in awaiting_review would otherwise sit there
        // forever and never reach Publer (the bug behind "published to LinkedIn
        // but nothing in Publer"). Mirrors PublishSlotOrchestrator's
        // scheduled-path dispatch. Idempotent via PublishViaPubler's
        // publer_post_id guard; siblings still generating are left to the
        // caption-gen completion hook.
        $draft->load(['instagramPost', 'tiktokPost', 'threadsPost', 'facebookPost']);
        foreach (['instagram', 'tiktok', 'threads', 'facebook'] as $platform) {
            $sibling = $draft->{$platform . 'Post'};
            if ($sibling !== null
                && in_array($sibling->status, ['awaiting_review', 'publishing'], true)
                && $sibling->publer_post_id === null) {
                try {
                    \App\Jobs\PublishViaPubler::dispatch($platform, $sibling->id);
                } catch (\Throwable $e) {
                    Log::warning('[LinkedInDraftController] direct sibling publish dispatch failed', [
                        'draft_id' => $draft->id,
                        'platform' => $platform,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
            'message' => 'LinkedIn published. Cross-post fan-out queued — IG/TikTok/Threads will publish via Publer (~1-3 min).',
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
        $slideCount = count($slides);

        // Block re-author when an in-flight FSM transition is still working —
        // dispatching /carousel-gen on a draft that's currently generating
        // would race with the existing pipeline.
        if (in_array($draft->status, ['pending_generation', 'generating', 'validating'], true)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'regenerate_in_progress',
                    'message' => 'A generation is already in progress for this draft.',
                ],
            ], 409);
        }

        // Cache lock to prevent double-dispatch race. The FSM-status guard
        // above doesn't catch this case because regenerate-images keeps the
        // draft in manual_review throughout — clicking the button twice in
        // quick succession would otherwise fire two /carousel-gen runs +
        // two image-render chains (18 GeminiGen calls instead of 9 + last-
        // writer-wins on carousel_slides JSON). TTL = 16 minutes covers the
        // 880s /carousel-gen timeout + 60s grace; lock is also released by
        // RegenerateLinkedInCarouselContent::handle()/failed() once the
        // job exits so a fresh dispatch is allowed immediately afterwards
        // (no need to wait out the full TTL on success).
        $lockKey = "linkedin_regenerate_lock:{$draft->id}";
        $acquired = Cache::add($lockKey, now()->toIso8601String(), now()->addSeconds(960));
        if (! $acquired) {
            $heldSince = Cache::get($lockKey);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'regenerate_lock_held',
                    'message' => 'A regeneration is already running for this draft. Wait for it to finish, or cancel and retry. (Lock acquired at ' . $heldSince . ')',
                ],
            ], 409);
        }

        // Re-author semantics. The button is labelled "Regenerate all images"
        // but the operator's actual intent is "give me fresh visual concepts"
        // — earlier behaviour only re-rendered the existing legacy prompt text
        // through GeminiGen, which produced the same conventional cover scene
        // (terminal frames, courtroom shots) on every retry. Operators
        // reasonably expect this button to fire the /carousel-gen plugin and
        // produce mindblowing absurdist hooks per the visual-hook gate
        // (wow_minimum 6/8, pattern interrupt, surreal scene metaphors).
        //
        // Flow:
        //   1. Optimistically reset all slide image_status to 'pending' so the
        //      UI status pills flip immediately (operator sees "Rendering..."
        //      placeholders rather than the stale rendered images).
        //   2. Clear slide_asset_urns + last_error — LinkedIn assets registered
        //      against old image bytes are no longer ownable by fresh renders,
        //      and any previously persisted error is no longer the truth.
        //   3. Dispatch RegenerateLinkedInCarouselContent job which:
        //        a) calls /carousel-gen plugin via SSH (~2-3 min)
        //        b) replaces carousel_slides JSON with fresh prompts
        //        c) chain-dispatches GenerateLinkedInCarouselImages to render
        //
        // Caption + hashtags + link_comment + draft ID + FSM state are all
        // preserved across the re-author, so operator's caption work survives.
        foreach ($slides as $i => $slide) {
            $slides[$i]['image_status'] = 'pending';
            $slides[$i]['image_url'] = '';
            $slides[$i]['image_error'] = null;
            $slides[$i]['image_job_uuid'] = null;
        }
        $draft->update([
            'carousel_slides' => $slides,
            'slide_asset_urns' => null,
            'last_error' => null,
        ]);

        \App\Jobs\RegenerateLinkedInCarouselContent::dispatch($draft->id);

        Log::info('[LinkedInDraft] regenerate-images queued (re-author via /carousel-gen)', [
            'draft_id' => $draft->id,
            'slide_count' => $slideCount,
        ]);

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
            'message' => "Re-authoring {$slideCount} slide(s) via /carousel-gen plugin (~2-3 min) then rendering images (~3-4 min). Total ~5-7 min — webhooks will update slides live as each finishes.",
            'queued' => $slideCount,
        ], 202);
    }

    /**
     * POST /admin/linkedin-drafts/{id}/rerender-images
     *
     * Operator escape hatch: re-renders slide images using the EXISTING
     * carousel_slides JSON without going through /carousel-gen. Use when
     * /carousel-gen keeps failing (Sonnet output truncation on 9-slide
     * bilingual carousels — see CLAUDE.md May 2 entry "Open issue") but
     * the slides JSON is intact and operator just wants the PNGs.
     *
     * Differs from regenerateAllImages: that one re-AUTHORS via
     * /carousel-gen (fresh prompts, ~5-7 min, can fail). This one only
     * re-RENDERS via GeminiGen using the prompts already in the DB
     * (~2-3 min, no Sonnet round-trip, can't hit truncation). Mirrors
     * what the carousel-image reaper does on stuck slides.
     */
    public function rerenderImagesOnly(int $id): JsonResponse
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
                    'message' => 'Image rerender is only valid for carousel drafts.',
                ],
            ], 422);
        }

        if (in_array($draft->status, ['pending_generation', 'generating', 'validating'], true)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'rerender_in_progress',
                    'message' => 'A generation is already in progress for this draft.',
                ],
            ], 409);
        }

        $slides = $draft->carousel_slides ?? [];
        $slideCount = count($slides);
        if ($slideCount === 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'no_slides',
                    'message' => 'This draft has no carousel slides to render. Use Regenerate All Images to author via /carousel-gen first.',
                ],
            ], 422);
        }

        // Verify each slide carries an image_prompt — otherwise GeminiGen has
        // nothing to render. This is the difference between "slides JSON
        // exists but is half-baked" vs "slides JSON is complete".
        $missingPrompts = 0;
        foreach ($slides as $slide) {
            if (empty($slide['image_prompt'] ?? null)) {
                $missingPrompts++;
            }
        }
        if ($missingPrompts > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'missing_image_prompts',
                    'message' => "{$missingPrompts} of {$slideCount} slides have no image_prompt. Use Regenerate All Images to author via /carousel-gen.",
                ],
            ], 422);
        }

        // Reset image status only — prompts + copy + brand chrome stay.
        foreach ($slides as $i => $slide) {
            $slides[$i]['image_status'] = 'pending';
            $slides[$i]['image_url'] = '';
            $slides[$i]['image_error'] = null;
            $slides[$i]['image_job_uuid'] = null;
        }
        $draft->update([
            'carousel_slides' => $slides,
            'slide_asset_urns' => null,
            'last_error' => null,
        ]);

        \App\Jobs\GenerateLinkedInCarouselImages::dispatch($draft->id);

        Log::info('[LinkedInDraft] rerender-images queued (skip /carousel-gen, use existing prompts)', [
            'draft_id' => $draft->id,
            'slide_count' => $slideCount,
        ]);

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
            'message' => "Rendering {$slideCount} slide(s) from existing prompts (~2-3 min). Webhooks will update slides live as each finishes.",
            'queued' => $slideCount,
        ], 202);
    }

    /**
     * POST /admin/linkedin-drafts/{id}/regenerate-caption
     *
     * Re-synthesizes caption + hashtags from existing slide content. Slide
     * images, FSM state, draft ID, and link_comment are preserved. Used when
     * operator finds slide images acceptable but wants the caption refreshed
     * (e.g., after editing slide copy, or when the initial caption was
     * generated by a pre-rewrite version of buildCarouselCaption).
     *
     * Synchronous (~1s, pure PHP synthesis — no SSH/Claude/queue), so the
     * frontend gets the new content in the response and can update the UI
     * immediately without polling.
     *
     * Carousel-only — text format's "caption" IS the post body, regenerating
     * it independently doesn't make sense for that format.
     */
    public function regenerateCaption(int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        // Block when an in-flight FSM transition is still working — caption
        // regen during generating/validating would be overwritten by the
        // pipeline's own persistAndRoute step the moment it completes.
        if (in_array($draft->status, ['pending_generation', 'generating', 'validating'], true)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'regenerate_in_progress',
                    'message' => 'A generation is already in progress for this draft.',
                ],
            ], 409);
        }

        $result = $this->generation->regenerateCaption($draft);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'caption_regen_failed',
                    'message' => $result['error'] ?? 'Caption regeneration failed.',
                ],
            ], 422);
        }

        Log::info('[LinkedInDraft] caption regenerated', [
            'draft_id' => $draft->id,
            'caption_length' => mb_strlen((string) ($result['content'] ?? '')),
            'hashtag_count' => count($result['hashtags'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
            'message' => 'Caption + hashtags regenerated from slides.',
        ]);
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
                'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
            'message' => "Slide {$slideIndex} re-dispatched.",
            'job_uuid' => $uuid,
        ]);
    }

    /**
     * GET /admin/linkedin-posts/calendar?from=2026-05-01&to=2026-05-31
     *
     * Lightweight date-range query for the calendar month grid.
     *
     * **Pin semantics** — drafts pin to the day they're meaningful TO THE
     * OPERATOR, which differs by status:
     *   - published     -> published_at         (terminal — when it shipped)
     *   - awaiting_publish -> cancel_window_ends_at (actual fire time —
     *                          what the cron checks; `scheduled_at` is the
     *                          immutable approval timestamp, NOT the publish
     *                          target. Operator picked publish_at via the
     *                          reschedule modal which writes
     *                          cancel_window_ends_at — see approve() ll.299-305.)
     *   - other         -> scheduled_at         (queue ordering fallback)
     *
     * Query OR-matches all three columns in [from,to] so a draft approved
     * yesterday but scheduled to publish today still shows on today's cell.
     */
    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string'], // optional client-side filter pill
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $query = LinkedInPost::query()
            ->with(['post.translations'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('cancel_window_ends_at', [$from, $to])
                    ->orWhereBetween('scheduled_at', [$from, $to])
                    ->orWhereBetween('published_at', [$from, $to]);
            });

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $rows = $query
            ->orderBy('cancel_window_ends_at')
            ->orderBy('scheduled_at')
            ->orderBy('published_at')
            ->get();

        $items = $rows->map(function (LinkedInPost $draft) {
            $title = $draft->post?->translations?->first()?->title ?? 'Untitled draft';
            // Pin to actual operator-meaningful time — see method docblock.
            $pinAt = $draft->published_at
                ?? $draft->cancel_window_ends_at
                ?? $draft->scheduled_at;
            return [
                'id' => $draft->id,
                'status' => $draft->status,
                'format' => $draft->format,
                'post_id' => $draft->post_id,
                'post_title' => $title,
                'scheduled_at' => $draft->scheduled_at?->toIso8601String(),
                'cancel_window_ends_at' => $draft->cancel_window_ends_at?->toIso8601String(),
                'published_at' => $draft->published_at?->toIso8601String(),
                'pin_at' => $pinAt?->toIso8601String(),
                'depth_score' => $draft->depth_score,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'items' => $items,
                'count' => $items->count(),
            ],
        ]);
    }

    /**
     * POST /admin/linkedin-drafts/{id}/check-conflict
     *
     * Check whether a proposed scheduled_at conflicts with other live drafts
     * within ±30 min window. Soft-warning data — operator decides whether to
     * proceed (LinkedIn distributes reach poorly when same-author posts
     * ship within ~30 min, but legitimate cases exist: announcement + Q&A).
     *
     * Returns:
     *   - has_conflict: bool
     *   - conflicts: [{id, post_title, scheduled_at, minutes_apart}]
     *   - suggested_alternatives: ["09:00", "11:30", ...] (top 3 hours that day
     *     with score >= 80, sourced from posting_time_rules table when present)
     *   - window_minutes: 30 (echo back so frontend can label the warning)
     */
    public function checkConflict(Request $request, int $id): JsonResponse
    {
        $draft = LinkedInPost::findOrFail($id);

        $validated = $request->validate([
            'at' => ['required', 'date'],
        ]);

        $proposed = Carbon::parse($validated['at']);
        $windowMinutes = LinkedInScheduleConflictService::DEFAULT_WINDOW_MINUTES;

        $conflicts = $this->conflictService->findConflicts($proposed, $id, $windowMinutes);

        // Suggested alternatives: pull from posting_time_rules if the table
        // exists + has rows for the proposed dow + linkedin/b2b_tech audience.
        // Phase A1 ships the migration; until then this gracefully degrades
        // to an empty array (frontend shows the existing static hint).
        $suggestedAlternatives = [];
        if (Schema::hasTable('posting_time_rules')) {
            $dow = $proposed->dayOfWeek; // Carbon: 0=Sun..6=Sat (matches schema)
            $suggestedAlternatives = DB::table('posting_time_rules')
                ->where('platform', 'linkedin')
                ->where('audience', 'b2b_tech')
                ->where('day_of_week', $dow)
                ->where('score', '>=', 80)
                ->orderByDesc('score')
                ->orderBy('hour')
                ->limit(3)
                ->pluck('hour')
                ->map(fn ($h) => sprintf('%02d:00', $h))
                ->values()
                ->all();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_conflict' => $conflicts->isNotEmpty(),
                'conflicts' => $conflicts,
                'suggested_alternatives' => $suggestedAlternatives,
                'window_minutes' => $windowMinutes,
            ],
        ]);
    }

    /**
     * POST /api/admin/linkedin-drafts/{id}/publish-all
     *
     * Cascade orchestrator — operator clicks one button, all 5 platforms
     * (LinkedIn + FB + IG + TT + Threads) flow through publish without
     * per-platform manual review.
     *
     * Steps:
     *   1. Validate LinkedIn status = awaiting_publish
     *   2. Set auto_approve_cross_posts=true so per-platform gen jobs
     *      auto-promote AwaitingReview → Publishing → dispatch Publer
     *      instead of waiting for operator review.
     *   3. Run publishNow() — LinkedIn live via direct API.
     *   4. Targeted scanner trigger via Artisan::queue with --draft-id.
     *      Scanner fans out FB/IG/TT/Threads rows, each generation job
     *      reads the auto-approve flag and chains to publish.
     *
     * Returns 202 with the LinkedIn publish result. Cross-post platforms
     * polled async via the existing per-sibling status (visible in the
     * "Sosmed health" indicator on the detail page).
     */
    public function publishAll(int $id): JsonResponse
    {
        $draft = LinkedInPost::find($id);
        if ($draft === null) {
            return $this->notFound();
        }

        if ($draft->status !== 'awaiting_publish') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'invalid_status',
                    'message' => "publish-all requires status 'awaiting_publish', got '{$draft->status}'",
                ],
            ], 422);
        }

        // Mark cascade BEFORE LinkedIn publish so any race condition
        // (e.g. cron-fired social-cross-post:scan firing in the same
        // window) still sees the flag and auto-approves.
        $draft->update(['auto_approve_cross_posts' => true]);

        $result = $this->publisher->publish($draft);
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'linkedin_publish_failed',
                    'message' => $result['error'] ?? 'LinkedIn publish failed',
                ],
                'data' => $draft,
            ], 503);
        }

        try {
            $this->guard->advance(
                $draft,
                LinkedInPostStatus::Published,
                'admin_publish_all',
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

        // Trigger fan-out scanner targeted to this draft only — bypasses
        // time + virality windows since the operator already opted in.
        try {
            \Illuminate\Support\Facades\Artisan::queue('social-cross-post:scan', [
                '--draft-id' => $draft->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[publishAll] Fan-out scanner dispatch failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'LinkedIn published. Cross-post fan-out queued — FB/IG/TT/Threads will publish automatically (~1-3 min). Watch the Sosmed health indicator.',
            'data' => $draft->fresh(['post.translations', 'post.contentIdea:id,result_post_id,virality_score', 'account']),
        ], 202);
    }

    /**
     * POST /api/admin/linkedin-drafts/scan-blog-now
     *
     * Manual operator-trigger for `linkedin:scan-blog`. Bypasses the daily
     * 03:00 WIB cron. Runs SYNCHRONOUSLY (Artisan::call, not ::queue) so the
     * operator gets immediate feedback about how many drafts were created.
     * The scan itself is just DB queries (fast); only the per-draft
     * GenerateLinkedInPost jobs dispatch async to the queue worker, which
     * happens in milliseconds — by the time this response returns, the new
     * drafts already exist in `linkedin_posts` with status=pending_generation
     * and the worker is picking them up.
     *
     * Defaults to 168h (7d) lookback window — wider than the daily cron's
     * 24h since manual scans typically target a specific recent article that
     * may have been missed for any reason.
     *
     * Optional payload:
     *   hours        int    1..720    lookback window in hours (default 168)
     *   min_virality int    0..100    override virality gate (0 = disable)
     */
    public function scanBlogNow(Request $request): JsonResponse
    {
        $hours = (int) $request->input('hours', 168);
        if ($hours < 1 || $hours > 720) {
            $hours = 168;
        }

        $args = ['--hours' => $hours];
        if ($request->has('min_virality')) {
            $minVirality = (int) $request->input('min_virality');
            if ($minVirality >= 0 && $minVirality <= 100) {
                $args['--min-virality'] = $minVirality;
            }
        }

        try {
            // Synchronous so we can return real counts. Capture output for
            // the operator to see which posts (if any) were picked up.
            $exitCode = Artisan::call('linkedin:scan-blog', $args);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('Manual linkedin:scan-blog dispatch failed', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'scan_failed',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }

        // Parse "Created N draft(s)." from the command output. Falls back
        // to "No un-converted posts" detection for the zero case.
        $created = 0;
        if (preg_match('/Created (\d+) draft/i', $output, $m)) {
            $created = (int) $m[1];
        }
        $foundZero = stripos($output, 'No un-converted posts') !== false;

        if ($created > 0) {
            $message = "Scan complete — {$created} new draft(s) created. They'll appear in the In Progress tab within ~30s as the worker picks them up.";
        } elseif ($foundZero) {
            $message = "Scan complete — no new posts to convert. Either every recent post in the last {$hours}h already has a draft, or no published posts pass the virality gate. Try increasing the window or setting min_virality=0.";
        } else {
            $message = "Scan complete (exit {$exitCode}). " . ($output ?: 'No output.');
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'hours' => $hours,
            'output' => $output,
            'message' => $message,
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
