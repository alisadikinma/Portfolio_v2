<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * IG repurpose Phase F — finalize. Branches on $job->mode (D1/D8/D9):
 *
 *   blog     → ContentIdea(article_ready, source=ig_repurpose) — enters the
 *              existing Content Engine pipeline; operator drives Gate-2 images +
 *              publish, which auto-fires the event-driven carousel + cross-post.
 *   carousel → anchor blog Post(draft, published=false) + primary PostTranslation
 *              + LinkedInPost(carousel, pending_generation), then dispatch
 *              GenerateLinkedInPost — the existing force-carousel path authors
 *              the slides from the anchor post body, renders (poller), and fans
 *              out cross-posts. (Maximal reuse — no synchronous /carousel-gen
 *              inside this job.)
 *
 * Success → status drafted + purge artifact dir (D6) + Telegram drafted notice.
 * Any failure → status failed + artifact dir RETAINED for retry/debug.
 *
 * Cross-schema note: prod `posts` has no title/content/excerpt columns (those
 * live in post_translations — see backend/CLAUDE.md), but the local/test sqlite
 * schema declares title/content NOT NULL. Schema::hasColumn guards make Post
 * creation correct on BOTH without a false green.
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class FinalizeRepurpose implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (!$job || $job->status !== RepurposeJobStatus::Rewritten->value) {
            return;
        }

        $rewritten = (array) ($job->rewritten ?? []);
        if (empty($rewritten['title']) || empty($rewritten['body'])) {
            $this->failJob($job, 'finalize_failed: rewritten title/body missing');
            return;
        }

        $job->transitionTo(RepurposeJobStatus::Finalizing, 'finalize_start');

        try {
            if ($job->mode === 'blog') {
                $this->finalizeBlog($job, $rewritten);
            } else {
                $this->finalizeCarousel($job, $rewritten);
            }
        } catch (\Throwable $e) {
            // Artifact dir retained (no purge) so the operator can retry.
            $this->failJob($job, 'finalize_exception: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $r
     */
    private function finalizeBlog(RepurposeJob $job, array $r): void
    {
        $idea = ContentIdea::create([
            'title' => (string) $r['title'],
            'status' => 'article_ready',
            // `source` is an enum column — 'instagram' is the closest allowed
            // value; the precise provenance lives in source_data.source below.
            'source' => 'instagram',
            'pillar' => $this->resolvePillar($job),
            'auto_mode' => false,
            'source_data' => ['source' => 'ig_repurpose', 'url' => $job->source_url],
            'generated_article' => [
                'language' => 'id',
                'title' => (string) $r['title'],
                'content' => (string) $r['body'],
                'excerpt' => (string) ($r['excerpt'] ?? ''),
                'meta_keywords' => (string) ($r['meta_keywords'] ?? ''),
                'sources_appendix' => array_values((array) ($r['sources_appendix'] ?? [])),
            ],
        ]);

        $job->transitionTo(
            RepurposeJobStatus::Drafted,
            'finalize_blog',
            ['content_idea_id' => $idea->id, 'last_error' => null]
        );
        $this->purge($job);
        app(TelegramNotificationService::class)->sendRepurposeDrafted($job, null, $this->correctedClaims($job));
    }

    /**
     * @param array<string,mixed> $r
     */
    private function finalizeCarousel(RepurposeJob $job, array $r): void
    {
        $slug = Str::slug((string) $r['title']);
        $slug = ($slug !== '' ? $slug : 'repurpose') . '-' . Str::lower(Str::random(6));

        $linkedinId = DB::transaction(function () use ($job, $r, $slug) {
            $postData = [
                'category_id' => $this->resolveCategoryId(),
                'slug' => $slug,
                'published' => false,
                'published_at' => null,
            ];
            // Only set body columns that actually exist (prod keeps them in
            // post_translations; test sqlite declares them NOT NULL on posts).
            foreach (['title' => (string) $r['title'], 'excerpt' => (string) ($r['excerpt'] ?? ''), 'content' => (string) $r['body']] as $col => $val) {
                if (Schema::hasColumn('posts', $col)) {
                    $postData[$col] = $val;
                }
            }
            $post = Post::create($postData);

            $post->translations()->create([
                'language' => 'id',
                'title' => (string) $r['title'],
                'slug' => $slug,
                'excerpt' => (string) ($r['excerpt'] ?? ''),
                'content' => (string) $r['body'],
                'meta_keywords' => (string) ($r['meta_keywords'] ?? ''),
            ]);

            $draft = LinkedInPost::create([
                'post_id' => $post->id,
                'format' => 'carousel',
                'content' => '', // NOT NULL; force-carousel path rebuilds the caption
                'hashtags' => [], // NOT NULL json
                'status' => 'pending_generation',
            ]);

            $job->transitionTo(
                RepurposeJobStatus::Drafted,
                'finalize_carousel',
                ['anchor_post_id' => $post->id, 'linkedin_post_id' => $draft->id, 'last_error' => null]
            );

            return $draft->id;
        });

        // Hand off to the existing carousel pipeline (force-carousel → /carousel-gen
        // → render via poller → cross-post fan-out). Dispatched after the
        // transaction commits so the worker sees a persisted draft.
        GenerateLinkedInPost::dispatch($linkedinId);

        // NOTE: captured IG slides are intentionally RETAINED here (no purge) so the
        // Social Studio detail can render the source↔generated image comparison.
        // `repurpose:reap` clears them ~7 days AFTER the carousel is published
        // (publish-anchored retention), or re-fetches them on demand via the
        // detail's "Re-fetch source" action (RefetchSourceSlides).
        app(TelegramNotificationService::class)->sendRepurposeDrafted($job, $linkedinId, $this->correctedClaims($job));
    }

    private function resolvePillar(RepurposeJob $job): string
    {
        // pillar is an enum column: vibe_coding|ai_automation|ai_agents|ai_video_image|general.
        $allowed = ['vibe_coding', 'ai_automation', 'ai_agents', 'ai_video_image', 'general'];
        $pillar = $job->contentIdea?->pillar;
        return is_string($pillar) && in_array($pillar, $allowed, true) ? $pillar : 'general';
    }

    private function correctedClaims(RepurposeJob $job): int
    {
        return (int) (($job->research['corrected_count'] ?? 0));
    }

    private function resolveCategoryId(): int
    {
        $id = Category::query()->value('id');
        if ($id !== null) {
            return (int) $id;
        }
        return (int) Category::create(['name' => 'Repurpose'])->id;
    }

    private function purge(RepurposeJob $job): void
    {
        $rel = (string) $job->slides_path;
        $dir = $rel !== '' ? storage_path('app/' . $rel) : storage_path('app/repurpose/' . $job->id);
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
