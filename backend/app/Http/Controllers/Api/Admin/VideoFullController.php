<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RepurposeJobStatus;
use App\Http\Controllers\Controller;
use App\Models\RepurposeJob;
use App\Models\VideoFullSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
