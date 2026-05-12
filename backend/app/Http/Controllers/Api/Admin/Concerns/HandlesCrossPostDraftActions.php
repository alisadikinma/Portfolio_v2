<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Exceptions\InvalidStateTransitionException;
use App\Jobs\PublishViaPubler;
use App\Services\PipelineGuard;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Shared CRUD action helpers for the 3 cross-post draft admin controllers
 * (Facebook, Instagram, TikTok). Each platform's controller mixes this
 * trait and overrides only the platform-specific bits via abstract
 * accessors:
 *
 *   - modelClass()       e.g. FacebookPost::class
 *   - statusEnumClass()  e.g. FacebookPostStatus::class
 *   - generateJobClass() e.g. GenerateFacebookPost::class
 *   - hashtagBounds()    e.g. [3, 5] for IG, [5, 8] for TikTok
 *   - captionMaxLength() e.g. 2200
 *
 * The transitions, validation rules, and response shape stay uniform.
 *
 * Routes (per platform, mounted under `/admin/{platform}-drafts/...`):
 *   GET    /                       index (filter by status, scope, format, per_page)
 *   GET    /{id}                   show
 *   PUT    /{id}                   update (caption, hashtags, link_url for FB)
 *   POST   /{id}/regenerate        soft-delete + create pending row + dispatch
 *   POST   /{id}/approve           awaiting_review → publishing (dispatch PublishViaPubler)
 *   POST   /{id}/cancel            any non-terminal → cancelled
 *   GET    /calendar               feed shape for calendar view
 */
trait HandlesCrossPostDraftActions
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** @return class-string<BackedEnum> */
    abstract protected function statusEnumClass(): string;

    /** @return class-string */
    abstract protected function generateJobClass(): string;

    /**
     * @return array{0: int, 1: int} [min, max] inclusive hashtag count.
     */
    abstract protected function hashtagBounds(): array;

    abstract protected function captionMaxLength(): int;

    /**
     * Whether this platform's draft model has a `format` ENUM column
     * (text|carousel) and a `link_url` column. Only Facebook does — IG and
     * TikTok carousel-only drafts have no format column.
     *
     * Subclass overrides as needed. Default = false.
     */
    protected function hasFormatColumn(): bool
    {
        return false;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'format' => ['nullable', Rule::in(['text', 'carousel'])],
            'scope' => ['nullable', Rule::in(['feed', 'queue', 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $modelClass = $this->modelClass();
        /** @var Builder $query */
        $query = $modelClass::query()
            ->with([
                'post' => fn ($q) => $q->select('id', 'slug', 'featured_image', 'published_at'),
                'post.translations' => fn ($q) => $q->select('id', 'post_id', 'language', 'title'),
                'linkedinPost' => fn ($q) => $q->select('id', 'post_id', 'format', 'status'),
            ])
            ->orderByDesc('updated_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // FB has format column; IG/TT don't — only filter when present.
        if (!empty($validated['format']) && $this->hasFormatColumn()) {
            $query->where('format', $validated['format']);
        }

        $statusEnum = $this->statusEnumClass();
        if (($validated['scope'] ?? null) === 'feed' && method_exists($statusEnum, 'feedStatuses')) {
            $query->whereIn('status', $statusEnum::feedStatuses());
        } elseif (($validated['scope'] ?? null) === 'queue' && method_exists($statusEnum, 'queueStatuses')) {
            $query->whereIn('status', $statusEnum::queueStatuses());
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
        $modelClass = $this->modelClass();
        $draft = $modelClass::with(['post.translations', 'linkedinPost'])->find($id);
        if ($draft === null) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $draft,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $modelClass = $this->modelClass();
        $draft = $modelClass::find($id);
        if ($draft === null) {
            return $this->notFoundResponse();
        }

        [$minTags, $maxTags] = $this->hashtagBounds();
        $rules = [
            'title' => ['nullable', 'string', 'max:200'],
            'caption' => ['nullable', 'string', 'max:' . $this->captionMaxLength()],
            'hashtags' => ['nullable', 'array', 'min:' . $minTags, 'max:' . $maxTags],
            'hashtags.*' => ['string', 'max:60'],
        ];
        if ($this->hasFormatColumn()) {
            $rules['link_url'] = ['nullable', 'url', 'max:2048'];
        }

        $validated = $request->validate($rules);

        // Operators can save partial drafts even when validation reports
        // failures — there's no FSM transition here, just persistence.
        $draft->fill($validated);
        $draft->save();

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(),
        ]);
    }

    public function regenerate(int $id): JsonResponse
    {
        $modelClass = $this->modelClass();
        /** @var Model|null $draft */
        $draft = $modelClass::find($id);
        if ($draft === null) {
            return $this->notFoundResponse();
        }

        // Refuse regenerate from the terminal Published state. Cancelled
        // and Failed are NOT terminal here — admin recovery from those
        // back to PendingGeneration is the documented FSM path (see
        // {Facebook,Instagram,Tiktok}PostStatus::TRANSITIONS).
        $statusEnum = $this->statusEnumClass();
        if ($draft->status === $statusEnum::Published->value) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'invalid_state',
                    'message' => "Cannot regenerate from terminal status={$draft->status}",
                ],
            ], 422);
        }

        // App-level invariant: one live draft per post. Soft-delete the
        // existing one, create a fresh pending_generation row.
        $postId = $draft->post_id;
        $linkedinPostId = $draft->linkedin_post_id ?? null;
        $format = $this->hasFormatColumn() ? ($draft->format ?? null) : null;

        $draft->delete(); // soft-delete

        $newRowAttrs = [
            'post_id' => $postId,
            'linkedin_post_id' => $linkedinPostId,
            'status' => $statusEnum::PendingGeneration->value,
            'pipeline_state_log' => [[
                'from' => 'regenerate',
                'to' => 'pending_generation',
                'reason' => 'admin_regenerate',
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
        if ($format !== null) {
            $newRowAttrs['format'] = $format;
        }

        $newDraft = $modelClass::create($newRowAttrs);

        $jobClass = $this->generateJobClass();
        $jobClass::dispatch($newDraft->id);

        return response()->json([
            'success' => true,
            'data' => $newDraft,
        ], 201);
    }

    public function approve(int $id): JsonResponse
    {
        // Phase H+ gate. Until real Publer transport ships, refuse approve
        // so drafts don't get marooned in `publishing` while the stub
        // PublishViaPubler is the only thing that fires.
        if (!config('social-cross-post.publer.enabled', false)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'publer_disabled',
                    'message' => 'Publer publishing is not yet enabled. Set PUBLER_PUBLISH_ENABLED=true in env once Phase H+ ships.',
                ],
            ], 503);
        }

        $modelClass = $this->modelClass();
        /** @var Model|null $draft */
        $draft = $modelClass::find($id);
        if ($draft === null) {
            return $this->notFoundResponse();
        }

        $statusEnum = $this->statusEnumClass();
        if ($draft->status !== $statusEnum::AwaitingReview->value) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'invalid_state',
                    'message' => "Approve requires status=awaiting_review, got status={$draft->status}",
                ],
            ], 422);
        }

        try {
            app(PipelineGuard::class)->advance(
                $draft,
                $statusEnum::Publishing,
                'admin_approved'
            );
        } catch (InvalidStateTransitionException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'fsm_blocked', 'message' => $e->getMessage()],
            ], 422);
        }

        // Publer dispatch (real impl shipped May 12 — see PublishViaPubler P4).
        // New signature is (string $platform, int $siblingPostId) — map the
        // FQN model class to the short platform name the job's loadSibling
        // method expects.
        $platform = match ($modelClass) {
            \App\Models\FacebookPost::class => 'facebook',
            \App\Models\InstagramPost::class => 'instagram',
            \App\Models\TiktokPost::class => 'tiktok',
            \App\Models\ThreadsPost::class => 'threads',
            default => throw new \InvalidArgumentException("Unknown cross-post model: {$modelClass}"),
        };
        PublishViaPubler::dispatch($platform, $draft->id);

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(),
        ]);
    }

    public function cancel(int $id): JsonResponse
    {
        $modelClass = $this->modelClass();
        /** @var Model|null $draft */
        $draft = $modelClass::find($id);
        if ($draft === null) {
            return $this->notFoundResponse();
        }

        $statusEnum = $this->statusEnumClass();
        if ($draft->status === $statusEnum::Cancelled->value
            || $draft->status === $statusEnum::Published->value) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'invalid_state',
                    'message' => "Cannot cancel from status={$draft->status}",
                ],
            ], 422);
        }

        try {
            app(PipelineGuard::class)->advance(
                $draft,
                $statusEnum::Cancelled,
                'admin_cancelled'
            );
        } catch (InvalidStateTransitionException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'fsm_blocked', 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $draft->fresh(),
        ]);
    }

    /**
     * Lightweight calendar feed — returns rows pinned to scheduled_at OR
     * published_at (whichever is set) within ?from=&to= range. Mirrors
     * LinkedInDraftController::calendar shape so the frontend Calendar
     * component (Phase J) can reuse helpers across platforms.
     */
    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfMonth();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfMonth()->copy()->addMonth();

        $modelClass = $this->modelClass();
        $query = $modelClass::query()
            ->with([
                'post' => fn ($q) => $q->select('id', 'slug'),
                'post.translations' => fn ($q) => $q->select('id', 'post_id', 'language', 'title'),
            ])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('scheduled_at', [$from, $to])
                    ->orWhereBetween('published_at', [$from, $to]);
            });

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $rows = $query->get()->map(function ($row) {
            $title = optional(
                $row->post?->translations?->firstWhere('language', 'en')
                    ?? $row->post?->translations?->first()
            )->title;

            return [
                'id' => $row->id,
                'status' => $row->status,
                'format' => $row->format ?? null,
                'post_id' => $row->post_id,
                'post_title' => $title,
                'scheduled_at' => $row->scheduled_at?->toIso8601String(),
                'published_at' => $row->published_at?->toIso8601String(),
                'pin_at' => $row->published_at?->toIso8601String() ?? $row->scheduled_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
        ]);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'not_found', 'message' => 'Draft not found'],
        ], 404);
    }
}
