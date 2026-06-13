<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LinkedInPostStatus;
use App\Enums\RepurposeJobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CaptureInstagramPost;
use App\Jobs\ComposeVideoCarousel;
use App\Jobs\ExtractSlideContent;
use App\Jobs\FinalizeRepurpose;
use App\Jobs\GenerateRebrandAssets;
use App\Jobs\RefetchSourceSlides;
use App\Jobs\ResearchRepurposeClaims;
use App\Jobs\RewriteRepurposeContent;
use App\Models\RepurposeJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin monitoring surface for the Telegram → IG repurpose pipeline
 * (docs/plans/2026-06-11-repurpose-telegram-progress-admin-panel.md, Part B).
 * List + detail + per-step retry + private slide-thumbnail read. All routes
 * sit behind auth:sanctum (registered in routes/api.php).
 */
class RepurposeJobController extends Controller
{
    /**
     * Map a failed-from FSM state → [resume guard state, step job class]. The
     * guard state is the status each step job requires before it will run.
     */
    private const RETRY_MAP = [
        'received'    => ['capturing', CaptureInstagramPost::class],
        'capturing'   => ['capturing', CaptureInstagramPost::class],
        'extracting'  => ['captured', ExtractSlideContent::class],
        'researching' => ['extracted', ResearchRepurposeClaims::class],
        'rewriting'   => ['researched', RewriteRepurposeContent::class],
        'finalizing'  => ['rewritten', FinalizeRepurpose::class],
        // video_rebrand asset branch — resume the exact failed step at its guard
        // state rather than restarting from capture. GenerateRebrandAssets is
        // idempotent (skips bookends already keyframe/veo 'done', re-runs the
        // failed one), so re-entering at `extracted` only regenerates what broke.
        'generating_assets' => ['extracted', GenerateRebrandAssets::class],
        'assets_ready'      => ['assets_ready', ComposeVideoCarousel::class],
        'compositing'       => ['assets_ready', ComposeVideoCarousel::class],
        'composed'          => ['composed', FinalizeRepurpose::class],
    ];

    public function index(Request $request): JsonResponse
    {
        $query = RepurposeJob::query()->with('linkedinPost')->orderByDesc('id');

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $valid = array_map(fn ($c) => $c->value, RepurposeJobStatus::cases());
            if (!in_array($status, $valid, true)) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'INVALID_STATUS', 'message' => 'Unknown status filter.'],
                ], 422);
            }
            $query->where('status', $status);
        }

        // Mirror the blog source's `scope=queue` gate: hide a job once its work has
        // moved off the Social Studio surface, so nothing is double-listed:
        //   carousel mode → linked LinkedInPost left queueStatuses (scheduled into the
        //                   Content Calendar / published / cancelled)
        //   blog mode     → handed off to Content Engine the moment finalizeBlog seeds a
        //                   ContentIdea (status drafted, content_idea_id set). The blog
        //                   work now lives entirely in /admin/content-engine, so the job
        //                   leaves Social Studio immediately — NOT only once the article
        //                   finally publishes.
        // Jobs with no downstream yet (in-flight repurpose, video_rebrand awaiting manual
        // download, drafted-not-routed) and carousel drafts still in the working queue
        // (incl. failed → Failed tab) all stay. Opt-in via ?exclude_settled=1 so any other
        // consumer keeps the full set.
        if ($request->boolean('exclude_settled')) {
            $queue = LinkedInPostStatus::queueStatuses();
            $query
                ->where(function ($q) use ($queue) {
                    $q->whereDoesntHave('linkedinPost')
                        ->orWhereHas('linkedinPost', fn ($p) => $p->whereIn('status', $queue));
                })
                // content_idea_id is set ONLY by the blog hand-off (finalizeBlog);
                // carousel/video jobs never set it, so this hides handed-off blog jobs
                // without touching the other modes.
                ->whereNull('content_idea_id');
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map(fn (RepurposeJob $j) => $this->compact($j))->all(),
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
        $job = RepurposeJob::find($id);
        if (!$job) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $job->id,
                'status' => $job->status,
                'mode' => $job->mode,
                'title' => $this->derivedTitle($job),
                'source_url' => $job->source_url,
                'angle' => $job->angle,
                'slides_path' => $job->slides_path,
                'slide_count' => $this->slideFiles($job)->count(),
                'video_slides' => $this->videoSlides($job),
                'extracted' => $job->extracted,
                'research' => $job->research,
                'rewritten' => $job->rewritten,
                'caption' => $job->rewritten['caption'] ?? null,
                'content_idea_id' => $job->content_idea_id,
                'linkedin_post_id' => $job->linkedin_post_id,
                'anchor_post_id' => $job->anchor_post_id,
                'last_error' => $job->last_error,
                'pipeline_state_log' => $job->pipeline_state_log ?? [],
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
            ],
        ]);
    }

    public function retry(int $id): JsonResponse
    {
        $job = RepurposeJob::find($id);
        if (!$job) {
            return $this->notFound();
        }

        if ($job->status !== RepurposeJobStatus::Failed->value) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FAILED', 'message' => 'Only a failed job can be retried.'],
            ], 422);
        }

        $failedFrom = $this->failedFromStep($job);
        [$guardState, $jobClass] = self::RETRY_MAP[$failedFrom]
            ?? self::RETRY_MAP['capturing']; // safe fallback: full restart from capture

        // Blog mode skips research+rewrite and enters FinalizeRepurpose at
        // `extracted` (not `rewritten`). Resume the failed finalize there.
        if ($job->mode === 'blog' && $failedFrom === 'finalizing') {
            $guardState = 'extracted';
        }

        // Re-running video_rebrand asset generation: zero the auto-recover budget
        // so the bounded PollRebrandAssets retry safety-net is live again for this
        // fresh attempt (a failed job already spent its MAX_RETRIES auto-retries),
        // and reset the failed/orphaned bookends so GenerateRebrandAssets re-runs
        // them immediately (its dispatchKeyframe skips kf='done', so a veo-failed
        // bookend would otherwise wait for the 5-min recover() pass). Done bookends
        // (e.g. an already-rendered CTA) keep their state and are skipped.
        $extra = ['last_error' => null];
        if ($job->mode === 'video_rebrand' && $guardState === 'extracted') {
            $extra['asset_retry_count'] = 0;
            $job->videoSlides()
                ->whereIn('role', [\App\Models\RepurposeVideoSlide::ROLE_HOOK, \App\Models\RepurposeVideoSlide::ROLE_CTA])
                ->where(fn ($q) => $q->where('keyframe_status', 'failed')->orWhereNull('keyframe_status')->orWhere('veo_status', 'failed'))
                ->update(['keyframe_status' => null, 'veo_status' => null, 'composited_status' => 'pending', 'last_error' => null]);
        }

        $job->transitionTo(
            RepurposeJobStatus::from($guardState),
            'admin_retry',
            $extra
        );
        $jobClass::dispatch($job->id);

        return response()->json([
            'success' => true,
            'message' => 'Retry dispatched.',
            'data' => ['status' => $job->status],
        ]);
    }

    /**
     * Re-download the source IG slides on demand (the reaper clears them a week
     * after publish). Dispatches the capture asynchronously; the detail view
     * polls slide_count until the images reappear. Review-only — no FSM change.
     */
    public function refetchSource(int $id): JsonResponse
    {
        $job = RepurposeJob::find($id);
        if (!$job) {
            return $this->notFound();
        }
        if ((string) $job->source_url === '') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NO_SOURCE', 'message' => 'Job has no source URL to re-fetch.'],
            ], 422);
        }

        RefetchSourceSlides::dispatch($job->id);

        return response()->json([
            'success' => true,
            'message' => 'Source re-fetch dispatched.',
        ], 202);
    }

    /**
     * Hard-delete a repurpose job + its captured-slide artifacts. Backs the
     * Social Studio "Delete" action for manual cleanup of stale/test jobs. Any
     * linked ContentIdea / LinkedInPost is left intact — those have their own
     * delete surfaces; this only removes the IG monitoring row + its private
     * slide dir (RepurposeJob has no SoftDeletes — the row is gone for good).
     */
    public function destroy(int $id): JsonResponse
    {
        $job = RepurposeJob::find($id);
        if (!$job) {
            return $this->notFound();
        }

        // Purge captured-slide artifacts (private local disk) so deleting a job
        // doesn't leak an orphaned dir the reaper would otherwise have to find.
        $rel = (string) $job->slides_path;
        if ($rel !== '') {
            Storage::disk('local')->deleteDirectory($rel);
        }

        // Remove child video-slide rows first (FK), then the job itself.
        $job->videoSlides()->delete();
        $job->delete();

        return response()->json([
            'success' => true,
            'message' => 'Repurpose job deleted.',
        ]);
    }

    /**
     * Serve the Nth captured slide image from the PRIVATE per-job storage dir.
     * The client passes only an integer index (route-constrained to digits) —
     * never a filename — so there is no path-traversal surface; the index maps
     * to the Nth sorted slide-*.jpg in the server-controlled slides_path.
     */
    public function slide(int $id, int $n): StreamedResponse|JsonResponse
    {
        $job = RepurposeJob::find($id);
        if (!$job || (string) $job->slides_path === '') {
            return $this->notFound();
        }

        $files = $this->slideFiles($job);
        if ($n < 0 || $n >= $files->count()) {
            return $this->notFound();
        }

        return Storage::disk('local')->response($files->values()[$n]);
    }

    /**
     * video_rebrand per-slide rows for the manual-download UI. Empty for the
     * blog/carousel modes. composited_path is a full public MP4 URL the frontend
     * links with `<a download>` — no streaming endpoint needed (the file lives on
     * the public disk). Ordered by carousel position (hook 0 → tools → cta N+1).
     *
     * @return array<int,array<string,mixed>>
     */
    private function videoSlides(RepurposeJob $job): array
    {
        if ($job->mode !== 'video_rebrand') {
            return [];
        }

        return $job->videoSlides()->get()->map(fn ($s) => [
            'id' => $s->id,
            'slide_index' => $s->slide_index,
            'role' => $s->role,
            'header_title' => $s->header_title,
            'keyframe_status' => $s->keyframe_status,
            'veo_status' => $s->veo_status,
            'composited_status' => $s->composited_status,
            'composited_url' => $s->composited_path, // full public MP4 URL (or null)
        ])->all();
    }

    /** Sorted list of slide-*.jpg paths (relative to the local disk) for a job. */
    private function slideFiles(RepurposeJob $job): \Illuminate\Support\Collection
    {
        $rel = (string) $job->slides_path;
        if ($rel === '') {
            return collect();
        }

        return collect(Storage::disk('local')->files($rel))
            ->filter(fn ($f) => str_starts_with(basename($f), 'slide-') && str_ends_with($f, '.jpg'))
            ->sort()
            ->values();
    }

    /** Last pipeline_state_log entry whose target was `failed` → its `from`. */
    private function failedFromStep(RepurposeJob $job): string
    {
        $log = $job->pipeline_state_log ?? [];
        for ($i = count($log) - 1; $i >= 0; $i--) {
            if (($log[$i]['to'] ?? null) === RepurposeJobStatus::Failed->value) {
                return (string) ($log[$i]['from'] ?? 'capturing');
            }
        }
        return 'capturing';
    }

    /** @return array<string,mixed> compact list-row shape. */
    private function compact(RepurposeJob $job): array
    {
        $slideCount = $this->slideFiles($job)->count();
        $render = $this->carouselRenderProgress($job);

        return [
            'id' => $job->id,
            'status' => $job->status,
            'mode' => $job->mode,
            'title' => $this->derivedTitle($job),
            'source_url' => $job->source_url,
            'angle' => $job->angle,
            'slide_count' => $slideCount,
            'has_cover' => $slideCount > 0,
            'cover_url' => $this->generatedCoverUrl($job),
            'render_state' => $render['state'],
            'render_done' => $render['done'],
            'render_total' => $render['total'],
            'reauthor_started_at' => $render['reauthor_started_at'],
            'content_idea_id' => $job->content_idea_id,
            'linkedin_post_id' => $job->linkedin_post_id,
            'anchor_post_id' => $job->anchor_post_id,
            'last_error' => $job->last_error,
            'created_at' => $job->created_at,
            'updated_at' => $job->updated_at,
        ];
    }

    /**
     * Public thumbnail fallback for a Social Studio IG card: the first generated
     * carousel slide image (a `done` public URL). Used when the PRIVATE source
     * slides have been purged (the reaper clears them a week after publish), so
     * the operator still sees a "1st image" on the list. Null when the job has
     * no linked carousel draft yet. Reads the eager-loaded `linkedinPost`.
     */
    private function generatedCoverUrl(RepurposeJob $job): ?string
    {
        $li = $job->relationLoaded('linkedinPost')
            ? $job->getRelation('linkedinPost')
            : ($job->linkedin_post_id ? $job->linkedinPost()->first() : null);

        foreach ((array) ($li->carousel_slides ?? []) as $slide) {
            $url = trim((string) ($slide['image_url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Render state + slide progress of the linked carousel draft so a Social
     * Studio IG card can reflect "Rendering images N/M" / "Re-authoring"
     * instead of a flat "Draft ready" while the downstream LinkedInPost slides
     * are still in flight. PHP mirror of socialStudioHelpers.js. Reads the
     * eager-loaded `linkedinPost` (index() does `->with('linkedinPost')`, so no
     * N+1). state=null for non-carousel jobs / no linked draft → the FE keeps
     * the plain FSM status.
     *
     * @return array{state: ?string, done: int, total: int, reauthor_started_at: ?string}
     */
    private function carouselRenderProgress(RepurposeJob $job): array
    {
        $empty = ['state' => null, 'done' => 0, 'total' => 0, 'reauthor_started_at' => null];

        if ($job->mode !== 'carousel') {
            return $empty;
        }

        $li = $job->relationLoaded('linkedinPost')
            ? $job->getRelation('linkedinPost')
            : ($job->linkedin_post_id ? $job->linkedinPost()->first() : null);

        if ($li === null || $li->format !== 'carousel') {
            return $empty;
        }

        $startedAt = \Illuminate\Support\Facades\Cache::get("linkedin_regenerate_lock:{$li->id}") ?: null;
        $slides = (array) ($li->carousel_slides ?? []);
        $total = count($slides);
        if ($total === 0) {
            return ['state' => 'pending', 'done' => 0, 'total' => 0, 'reauthor_started_at' => $startedAt];
        }

        $done = $inFlight = $failed = $reauthoring = 0;
        foreach ($slides as $slide) {
            $s = $slide['image_status'] ?? null;
            if ($s === 'reauthoring') {
                $reauthoring++;
            } elseif ($s === 'done' && !empty($slide['image_url'])) {
                $done++;
            } elseif ($s === 'generating') {
                $inFlight++;
            } elseif ($s === 'failed') {
                $failed++;
            }
        }

        $state = 'pending';
        if ($reauthoring > 0) {
            $state = 'reauthoring';
        } elseif ($done === $total) {
            $state = 'ready';
        } elseif ($inFlight > 0) {
            $state = 'generating';
        } elseif ($failed === $total) {
            $state = 'failed';
        } elseif ($done > 0 || $failed > 0) {
            $state = 'partial';
        }

        return ['state' => $state, 'done' => $done, 'total' => $total, 'reauthor_started_at' => $startedAt];
    }

    /**
     * Human topic title for a Social Studio card (operator "gak tau topiknya").
     * Priority: the rewritten title → first non-empty line of the source
     * caption (≤120 chars) → null. Both `rewritten` and `extracted` are array
     * casts, so we read them as arrays.
     */
    private function derivedTitle(RepurposeJob $job): ?string
    {
        $rewrittenTitle = trim((string) ($job->rewritten['title'] ?? ''));
        if ($rewrittenTitle !== '') {
            return $rewrittenTitle;
        }

        $caption = (string) ($job->extracted['caption'] ?? '');
        foreach (preg_split('/\r\n|\r|\n/', $caption) as $line) {
            $line = trim($line);
            if ($line !== '') {
                return mb_substr($line, 0, 120);
            }
        }

        return null;
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'NOT_FOUND', 'message' => 'Repurpose job not found.'],
        ], 404);
    }
}
