<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RepurposeJobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\PublishRepurposeViaZernio;
use App\Models\RepurposeJob;
use App\Models\VideoFullSegment;
use App\Services\ZernioPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin side of video_full — list / detail / per-segment regenerate. Heavy work
 * stays on the MacBook worker; these endpoints only read state and re-queue.
 *
 * @see docs/plans/2026-06-16-video-full-rebrand.md Phase E + H
 */
class VideoFullController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = RepurposeJob::query()
            ->where('mode', RepurposeJob::MODE_VIDEO_FULL)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->withCount('videoFullSegments')
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json($jobs);
    }

    public function show(int $id): JsonResponse
    {
        $job = RepurposeJob::where('mode', RepurposeJob::MODE_VIDEO_FULL)
            ->with('videoFullSegments')
            ->findOrFail($id);

        return response()->json([
            'job' => $job,
            'segments' => $job->videoFullSegments,
            'worker_online' => $job->worker_heartbeat_at && $job->worker_heartbeat_at->gt(now()->subMinutes(3)),
        ]);
    }

    /** Re-queue one segment: mark it pending + bounce the job to processing_local. */
    public function regenerateSegment(int $id, int $n): JsonResponse
    {
        $job = RepurposeJob::where('mode', RepurposeJob::MODE_VIDEO_FULL)->findOrFail($id);
        $seg = VideoFullSegment::where('repurpose_job_id', $job->id)->where('segment_index', $n)->firstOrFail();
        $seg->update(['status' => 'pending', 'last_error' => null]);

        if ($job->status === RepurposeJobStatus::Uploaded->value) {
            $job->transitionTo(RepurposeJobStatus::ProcessingLocal, "regenerate segment {$n}");
        }

        return response()->json(['ok' => true, 'segment_index' => $n, 'job_status' => $job->status]);
    }

    /**
     * Publish the final reel to LinkedIn/IG/TikTok/Threads via Zernio. A single MP4
     * is the simplest Zernio case (every platform supports single video). Per
     * platform: skip when no Zernio account is configured (LinkedIn-via-Zernio
     * needs zernio_linkedin_account_id — else it falls into `skipped`).
     */
    public function publishZernio(int $id, Request $request): JsonResponse
    {
        $job = RepurposeJob::where('mode', RepurposeJob::MODE_VIDEO_FULL)->findOrFail($id);

        if (! (bool) config('social-cross-post.zernio.enabled', false)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ZERNIO_DISABLED', 'message' => 'Zernio publishing is disabled. Enable ZERNIO_PUBLISH_ENABLED first.'],
            ], 503);
        }

        $data = $request->validate([
            'platforms' => 'sometimes|array|min:1',
            'platforms.*' => 'in:linkedin,instagram,tiktok,threads',
            'scheduled_at' => 'sometimes|nullable|date',
        ]);

        if (trim((string) $job->final_video_url) === '') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NO_FINAL_VIDEO', 'message' => 'The final reel has not been uploaded by the worker yet.'],
            ], 422);
        }

        $scheduledForIso = null;
        if (! empty($data['scheduled_at'])) {
            $when = Carbon::parse($data['scheduled_at']);
            if (! $when->isFuture()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'SCHEDULE_IN_PAST', 'message' => 'scheduled_at must be a future time.'],
                ], 422);
            }
            $scheduledForIso = $when->toIso8601String();
        }

        $platforms = array_values(array_unique($data['platforms'] ?? ['instagram', 'tiktok', 'threads']));
        $dispatched = [];
        $skipped = [];
        foreach ($platforms as $platform) {
            if (! ZernioPayloadBuilder::isPlatformEnabled($platform)) {
                $skipped[] = $platform;

                continue;
            }
            PublishRepurposeViaZernio::dispatch($job->id, $platform, $scheduledForIso);
            $dispatched[] = $platform;
        }

        return response()->json(['success' => true, 'dispatched' => $dispatched, 'skipped' => $skipped], 202);
    }
}
