<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LinkedInPostStatus;
use App\Enums\RepurposeJobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ComposeToolSlides;
use App\Jobs\GenerateRebrandAssets;
use App\Jobs\RefetchSourceSlides;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\RepurposeRetryService;
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

    public function retry(int $id, RepurposeRetryService $retryService): JsonResponse
    {
        $job = RepurposeJob::find($id);
        if (!$job) {
            return $this->notFound();
        }

        $result = $retryService->retry($job);
        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FAILED', 'message' => $result['message']],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => ['status' => $result['status']],
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
     * Re-render EVERY slide of a video_rebrand carousel (batch). Resets the
     * hook/CTA Veo bookends (keyframe + clip re-render — burns Veo credits) AND
     * the tool slides (free ffmpeg re-skin), forces the job back to `extracted`,
     * then dispatches GenerateRebrandAssets — which re-authors the hook, re-fires
     * both bookend keyframes, and dispatches ComposeToolSlides for the tools. The
     * PollRebrandAssets cron drives keyframe→Veo→composite→finalize. video_rebrand
     * only.
     */
    public function regenerateAllSlides(int $id): JsonResponse
    {
        $job = RepurposeJob::find($id);
        if (!$job) {
            return $this->notFound();
        }
        if ($job->mode !== 'video_rebrand') {
            return $this->notVideoRebrand();
        }

        $job->videoSlides()
            ->whereIn('role', [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA])
            ->update($this->bookendResetPayload());
        $job->videoSlides()
            ->where('role', RepurposeVideoSlide::ROLE_TOOL)
            ->update($this->toolResetPayload());

        $job->forceStatus(RepurposeJobStatus::Extracted, 'admin_regenerate_all_slides', [
            'asset_retry_count' => 0,
            'last_error' => null,
        ]);
        GenerateRebrandAssets::dispatch($job->id);

        return response()->json([
            'success' => true,
            'message' => 'All slides queued for regeneration.',
            'data' => ['status' => $job->status],
        ], 202);
    }

    /**
     * Re-render a SINGLE slide by its slide_index. A `tool` slide re-skins via
     * ComposeToolSlides (free, FSM-neutral — runs even on a `drafted` job; the
     * other done tools are skipped). A `hook`/`cta` bookend resets its keyframe +
     * clip and forces `extracted` → GenerateRebrandAssets, which re-renders only
     * that bookend (dispatchKeyframe skips slides already `done`/`generating`) and
     * leaves done tools untouched. video_rebrand only.
     */
    public function regenerateSlide(int $id, int $n): JsonResponse
    {
        $job = RepurposeJob::find($id);
        if (!$job) {
            return $this->notFound();
        }
        if ($job->mode !== 'video_rebrand') {
            return $this->notVideoRebrand();
        }

        $slide = $job->videoSlides()->where('slide_index', $n)->first();
        if (!$slide) {
            return $this->notFound();
        }

        if ($slide->role === RepurposeVideoSlide::ROLE_TOOL) {
            // Free re-skin — composite only this slide (the rest stay done → skipped).
            // FSM-neutral: ComposeToolSlides never transitions the job, so a `drafted`
            // job stays drafted and the widened detail poll picks up the live status.
            $slide->update($this->toolResetPayload());
            ComposeToolSlides::dispatch($job->id);
        } else {
            // Bookend — re-render keyframe + Veo clip (burns Veo credits).
            $slide->update($this->bookendResetPayload());
            $job->forceStatus(RepurposeJobStatus::Extracted, "admin_regenerate_slide:{$slide->role}", [
                'asset_retry_count' => 0,
                'last_error' => null,
            ]);
            GenerateRebrandAssets::dispatch($job->id);
        }

        return response()->json([
            'success' => true,
            'message' => "Slide #{$n} ({$slide->role}) queued for regeneration.",
            'data' => ['status' => $job->status],
        ], 202);
    }

    /** Reset payload for a Veo bookend so GenerateRebrandAssets re-renders it. */
    private function bookendResetPayload(): array
    {
        return [
            'keyframe_status' => null,
            'keyframe_job_uuid' => null,
            'keyframe_url' => null,
            'veo_status' => null,
            'veo_job_uuid' => null,
            'veo_url' => null,
            'composited_status' => 'pending',
            'composited_path' => null,
            'last_error' => null,
            'figure_dropped' => 0,
        ];
    }

    /** Reset payload for a tool slide so ComposeToolSlides re-skins it. */
    private function toolResetPayload(): array
    {
        return [
            'composited_status' => 'pending',
            'composited_path' => null,
            'last_error' => null,
        ];
    }

    private function notVideoRebrand(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'NOT_VIDEO_REBRAND', 'message' => 'Slide regeneration is only available for video_rebrand jobs.'],
        ], 422);
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
            // Cache-bust by updated_at: a re-skin overwrites the SAME file path
            // (slide_{i}.mp4), so without a version param the browser + Cloudflare
            // serve the stale MP4 and the operator never sees a re-render.
            'composited_url' => $s->composited_path
                ? $s->composited_path.'?v='.($s->updated_at?->timestamp ?? 0)
                : null, // full public MP4 URL (or null)
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
