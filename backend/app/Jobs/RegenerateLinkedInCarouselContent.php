<?php

namespace App\Jobs;

use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Services\CarouselGenOutputAdapter;
use App\Services\LinkedInGenerationService;
use App\Services\RepurposeCarouselBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Re-author carousel prompts in-place via /carousel-gen plugin, then
 * dispatch image rendering. Used by the admin "Regenerate all images"
 * button when the operator wants fresh visual concepts (absurdist hooks,
 * pattern interrupts, wow_minimum 6/8) — not just a re-render of the
 * existing legacy prompt text.
 *
 * Distinct from GenerateLinkedInPost (which goes through the full
 * /linkedin-gen orchestrator + FSM dance with brief generation) — this
 * job invokes /carousel-gen directly on the existing draft and replaces
 * carousel_slides JSON in place. Caption + hashtags + link_comment +
 * draft ID + FSM state stay untouched, so operator's caption edits and
 * approval state are preserved across the re-author.
 *
 * Distinct from GenerateLinkedInCarouselImages (which only re-renders
 * existing prompt text via GeminiGen) — this job authors NEW prompts
 * before triggering renders.
 *
 * Runtime: /carousel-gen ~2-3 min + chained image renders ~3-4 min.
 * Queued so the operator request returns in <1s.
 */
class RegenerateLinkedInCarouselContent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public readonly int $draftId)
    {
    }

    /**
     * Drop duplicate dispatches silently (dontRelease) rather than requeueing.
     * expireAfter exceeds the job $timeout + worker --timeout margin so the lock
     * never outlives a legitimately in-flight run.
     */
    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping($this->draftId))
                ->dontRelease()
                ->expireAfter(1320), // > $timeout(1200) + worker --timeout(1260) margin
        ];
    }

    public function handle(
        LinkedInGenerationService $generation,
        CarouselGenOutputAdapter $adapter
    ): void {
        try {
            $this->run($generation, $adapter);
        } finally {
            // Always release the dispatch lock — the controller acquired it
            // to prevent double-clicks; both success and failure must clear
            // it so the operator can retry without waiting out the full TTL.
            Cache::forget("linkedin_regenerate_lock:{$this->draftId}");
        }
    }

    private function run(
        LinkedInGenerationService $generation,
        CarouselGenOutputAdapter $adapter
    ): void {
        $draft = LinkedInPost::find($this->draftId);
        if (! $draft) {
            Log::warning('[RegenerateCarouselContent] draft not found', ['draft_id' => $this->draftId]);
            return;
        }

        if ($draft->format !== 'carousel') {
            Log::info('[RegenerateCarouselContent] not a carousel — skipping', [
                'draft_id' => $this->draftId,
                'format' => $draft->format,
            ]);
            return;
        }

        $draft->loadMissing('post');
        $post = $draft->post;
        if (! $post) {
            Log::warning('[RegenerateCarouselContent] draft has no post — skipping', ['draft_id' => $this->draftId]);
            return;
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        $slug = trim((string) ($post->slug ?? ''));
        if ($appUrl === '' || $slug === '') {
            Log::warning('[RegenerateCarouselContent] cannot resolve blog URL', [
                'draft_id' => $this->draftId,
                'app_url' => $appUrl,
                'slug' => $slug,
            ]);
            return;
        }
        $blogUrl = "{$appUrl}/blog/{$slug}";

        // Brief is only used by inferTargetSlides → default 7 (post May 4
        // 2026 reduction from 9). /carousel-gen reads blog content fresh
        // via --blog-source so it doesn't need our brief metadata.
        $brief = [];

        // Mirror the generate path so the "Regenerate all images" button
        // produces the SAME carousel as the auto pipeline: resolve the visual
        // style from the operator setting (sketchnote default; flips to
        // cinematic without redeploy) and detect IG-repurpose drafts so they
        // get --narrative=free (foreshadow dropped) instead of 5act. Without
        // this the button silently re-authored every draft as a 5act carousel
        // and ignored a cinematic revert.
        $style = (string) Setting::get('linkedin_carousel_style', 'sketchnote');
        $isRepurpose = $generation->isRepurposeDraft($draft);

        // Source-mirror path: for IG-repurpose carousels, build one slide per source
        // tool instead of calling /carousel-gen. The 20-min job budget (vs 360s for the
        // auto-pipeline) makes sequential per-slide authoring safe here. Falls back to
        // /carousel-gen when the builder returns [] (no parseable tool list in caption).
        if ($isRepurpose && config('linkedin.repurpose_source_mirror_regenerate', true)) {
            $sourceSlides = app(RepurposeCarouselBuilder::class)->buildForDraftId($this->draftId);
            if ($sourceSlides !== []) {
                $draft->update([
                    'carousel_slides'  => $sourceSlides,
                    'slide_asset_urns' => null,
                    'last_error'       => null,
                ]);
                Log::info('[RegenerateCarouselContent] source-mirror slides assembled (skipping /carousel-gen)', [
                    'draft_id'    => $this->draftId,
                    'slide_count' => count($sourceSlides),
                ]);
                GenerateLinkedInCarouselImages::dispatch($this->draftId);
                return;
            }
            Log::info('[RegenerateCarouselContent] source-mirror yielded no slides — falling back to /carousel-gen', [
                'draft_id' => $this->draftId,
            ]);
        }

        Log::info('[RegenerateCarouselContent] dispatching /carousel-gen', [
            'draft_id' => $this->draftId,
            'blog_url' => $blogUrl,
            'style' => $style,
            'is_repurpose' => $isRepurpose,
        ]);

        $envelope = $generation->dispatchCarouselGenEngine(
            $brief,
            $blogUrl,
            $this->draftId,
            null,
            $isRepurpose,
            $style
        );
        if ($envelope === null) {
            Log::error('[RegenerateCarouselContent] /carousel-gen returned null', ['draft_id' => $this->draftId]);
            $draft->update(['last_error' => 'Re-author failed: /carousel-gen returned no usable output. See logs.']);
            return;
        }

        try {
            $newSlides = $adapter->adapt($envelope);
        } catch (\Throwable $e) {
            Log::error('[RegenerateCarouselContent] adapter failed', [
                'draft_id' => $this->draftId,
                'error' => $e->getMessage(),
            ]);
            $draft->update(['last_error' => 'Re-author failed at adapter: ' . $e->getMessage()]);
            return;
        }

        if (empty($newSlides)) {
            Log::warning('[RegenerateCarouselContent] adapter returned empty slides', ['draft_id' => $this->draftId]);
            $draft->update(['last_error' => 'Re-author returned zero slides. Try again or use Regenerate copy.']);
            return;
        }

        $draft->update([
            'carousel_slides' => $newSlides,
            'slide_asset_urns' => null,
            'last_error' => null,
        ]);

        Log::info('[RegenerateCarouselContent] slides replaced; dispatching image renders', [
            'draft_id' => $this->draftId,
            'slide_count' => count($newSlides),
        ]);

        GenerateLinkedInCarouselImages::dispatch($this->draftId);
    }

    public function failed(\Throwable $exception): void
    {
        // Release dispatch lock on terminal failure too — handle()'s finally
        // covers in-job exceptions but Laravel may also call failed() on
        // serialization errors / max-attempts exceeded before handle() ever
        // runs, so we double up here.
        Cache::forget("linkedin_regenerate_lock:{$this->draftId}");

        Log::error('[RegenerateCarouselContent] job failed', [
            'draft_id' => $this->draftId,
            'error' => $exception->getMessage(),
        ]);
        try {
            $draft = LinkedInPost::find($this->draftId);
            $draft?->update(['last_error' => 'Re-author job crashed: ' . $exception->getMessage()]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
