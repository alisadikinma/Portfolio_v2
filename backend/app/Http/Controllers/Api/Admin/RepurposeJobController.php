<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RepurposeJobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CaptureInstagramPost;
use App\Jobs\ExtractSlideContent;
use App\Jobs\FinalizeRepurpose;
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
    ];

    public function index(Request $request): JsonResponse
    {
        $query = RepurposeJob::query()->orderByDesc('id');

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
                'source_url' => $job->source_url,
                'angle' => $job->angle,
                'slides_path' => $job->slides_path,
                'slide_count' => $this->slideFiles($job)->count(),
                'extracted' => $job->extracted,
                'research' => $job->research,
                'rewritten' => $job->rewritten,
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

        [$guardState, $jobClass] = self::RETRY_MAP[$this->failedFromStep($job)]
            ?? self::RETRY_MAP['capturing']; // safe fallback: full restart from capture

        $job->transitionTo(
            RepurposeJobStatus::from($guardState),
            'admin_retry',
            ['last_error' => null]
        );
        $jobClass::dispatch($job->id);

        return response()->json([
            'success' => true,
            'message' => 'Retry dispatched.',
            'data' => ['status' => $job->status],
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
        return [
            'id' => $job->id,
            'status' => $job->status,
            'mode' => $job->mode,
            'source_url' => $job->source_url,
            'angle' => $job->angle,
            'content_idea_id' => $job->content_idea_id,
            'linkedin_post_id' => $job->linkedin_post_id,
            'anchor_post_id' => $job->anchor_post_id,
            'last_error' => $job->last_error,
            'created_at' => $job->created_at,
            'updated_at' => $job->updated_at,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'NOT_FOUND', 'message' => 'Repurpose job not found.'],
        ], 404);
    }
}
