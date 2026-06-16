<?php

namespace App\Http\Controllers\Api;

use App\Enums\RepurposeJobStatus;
use App\Http\Controllers\Controller;
use App\Models\RepurposeJob;
use App\Models\VideoFullSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * VPS↔MacBook bridge — WORKER side. Every route is gated by a Sanctum token with
 * the `video-full:work` ability (see routes/api.php). The MacBook Node daemon
 * claims a queued job, streams progress + the segment manifest, and uploads the
 * rendered assets back. The VPS never runs the heavy pipeline.
 *
 * @see docs/plans/2026-06-16-video-full-rebrand.md Phase E
 */
class VideoFullWorkerController extends Controller
{
    /** Atomically claim the oldest queued_local video_full job (single-worker safe). */
    public function claim(): JsonResponse
    {
        $job = DB::transaction(function () {
            $j = RepurposeJob::query()
                ->where('mode', RepurposeJob::MODE_VIDEO_FULL)
                ->where('status', RepurposeJobStatus::QueuedLocal->value)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();
            if (! $j) {
                return null;
            }
            $j->transitionTo(RepurposeJobStatus::ClaimedLocal, 'worker claimed');
            $j->forceFill(['worker_claimed_at' => now(), 'worker_heartbeat_at' => now(), 'worker_progress' => 0])->save();

            return $j;
        });

        return response()->json(['job' => $job ? [
            'id' => $job->id,
            'source_url' => $job->source_url,
            'angle' => $job->angle,
        ] : null]);
    }

    /** Heartbeat + progress; first call advances claimed_local → processing_local. */
    public function progress(Request $request, int $id): JsonResponse
    {
        $job = $this->job($id);
        $data = $request->validate([
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'step' => ['nullable', 'string', 'max:32'],
        ]);
        if ($job->status === RepurposeJobStatus::ClaimedLocal->value) {
            $job->transitionTo(RepurposeJobStatus::ProcessingLocal, 'worker processing');
        }
        $job->forceFill([
            'worker_progress' => $data['progress'] ?? $job->worker_progress,
            'worker_step' => $data['step'] ?? $job->worker_step,
            'worker_heartbeat_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    /** Upsert the segment manifest (idempotent on segment_index). */
    public function segments(Request $request, int $id): JsonResponse
    {
        $job = $this->job($id);
        $data = $request->validate([
            'segments' => ['required', 'array', 'max:200'],
            'segments.*.segment_index' => ['required', 'integer', 'min:0'],
            'segments.*.type' => ['required', 'string', 'in:to_camera,b_roll,split_screen'],
            'segments.*.start_sec' => ['nullable', 'numeric'],
            'segments.*.end_sec' => ['nullable', 'numeric'],
            'segments.*.source_text_en' => ['nullable', 'string'],
            'segments.*.text_id' => ['nullable', 'string'],
            'segments.*.strategy' => ['nullable', 'string', 'max:32'],
        ]);
        foreach ($data['segments'] as $s) {
            VideoFullSegment::updateOrCreate(
                ['repurpose_job_id' => $job->id, 'segment_index' => $s['segment_index']],
                [
                    'type' => $s['type'],
                    'start_sec' => $s['start_sec'] ?? 0,
                    'end_sec' => $s['end_sec'] ?? 0,
                    'source_text_en' => $s['source_text_en'] ?? null,
                    'text_id' => $s['text_id'] ?? null,
                    'strategy' => $s['strategy'] ?? null,
                    'status' => 'processing',
                ],
            );
        }

        return response()->json(['ok' => true, 'count' => count($data['segments'])]);
    }

    /**
     * Receive a rendered asset: the final MP4 (→ uploaded) or a per-segment preview.
     * Files are stored under storage/app/public/video-full/{jobId}/ with Laravel's
     * hashed names (no path traversal from client-supplied names).
     */
    public function assets(Request $request, int $id): JsonResponse
    {
        $job = $this->job($id);
        $request->validate([
            'final_video' => ['nullable', 'file', 'mimetypes:video/mp4', 'max:716800'],   // 700 MB
            'segment_index' => ['nullable', 'integer', 'min:0'],
            'preview' => ['nullable', 'file', 'mimetypes:video/mp4,image/jpeg,image/png', 'max:204800'],
            'segment_status' => ['nullable', 'string', 'in:done,failed,dropped'],
        ]);

        if ($request->hasFile('final_video')) {
            $path = $request->file('final_video')->store("video-full/{$job->id}", 'public');
            $job->forceFill(['final_video_url' => Storage::disk('public')->url($path), 'worker_progress' => 100, 'worker_heartbeat_at' => now()])->save();
            if ($job->status === RepurposeJobStatus::ProcessingLocal->value) {
                $job->transitionTo(RepurposeJobStatus::Uploaded, 'final video uploaded');
            }
        }

        if ($request->filled('segment_index')) {
            $seg = VideoFullSegment::where('repurpose_job_id', $job->id)
                ->where('segment_index', $request->integer('segment_index'))->first();
            if ($seg) {
                $attrs = ['status' => $request->input('segment_status', 'done')];
                if ($request->hasFile('preview')) {
                    $attrs['preview_url'] = Storage::disk('public')->url(
                        $request->file('preview')->store("video-full/{$job->id}/segments", 'public')
                    );
                }
                $seg->update($attrs);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function job(int $id): RepurposeJob
    {
        return RepurposeJob::where('mode', RepurposeJob::MODE_VIDEO_FULL)->findOrFail($id);
    }
}
