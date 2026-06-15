<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\TelegramNotificationService;
use App\Services\VideoCarouselAnchorService;
use App\Support\CreatorHandle;
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
 *   blog     → ContentIdea(draft, source=ig_repurpose) seeded with an
 *              `instructions` brief from the extracted IG material (enters at
 *              `extracted`, skipping research+rewrite). Operator clicks
 *              "Start Research" to run the proper Content Engine pipeline
 *              (article-prep → write → 5-gate score → images → publish), which
 *              auto-fires the event-driven carousel + cross-post. NO generated
 *              article is written here — the old internal rewrite was low quality.
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

    /**
     * Default virality_score for a blog-mode repurpose idea. Manual IG curation
     * is high-intent, so seed comfortably above the LinkedIn scan gate
     * (linkedin_virality_min_score, default 60) — see finalizeBlog().
     */
    private const REPURPOSE_VIRALITY_SCORE = 75;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (!$job) {
            return;
        }

        // video_rebrand enters at `composed` (after ComposeVideoCarousel) and has
        // no claims to rewrite — it ships composited 4:5 MP4s the operator
        // downloads + posts manually (v1, no auto-publish). Branch off early.
        if ($job->mode === 'video_rebrand') {
            if ($job->status !== RepurposeJobStatus::Composed->value) {
                return;
            }
            $job->transitionTo(RepurposeJobStatus::Finalizing, 'finalize_video_start');
            try {
                $this->finalizeVideoRebrand($job);
            } catch (\Throwable $e) {
                // Composited files retained (no purge) so the operator can retry/download.
                $this->failJob($job, 'finalize_video_exception: ' . $e->getMessage());
            }
            return;
        }

        // Blog mode (June 13, 2026) enters at `extracted` — it skips the internal
        // research+rewrite (low quality, no scoring) and instead seeds a `draft`
        // ContentIdea from the IG material. The operator drives the proper Content
        // Engine pipeline (research → write → 5-gate score → images → publish →
        // auto carousel + cross-post).
        if ($job->mode === 'blog') {
            if ($job->status !== RepurposeJobStatus::Extracted->value) {
                return;
            }
            $job->transitionTo(RepurposeJobStatus::Finalizing, 'finalize_blog_start');
            try {
                $this->finalizeBlog($job);
            } catch (\Throwable $e) {
                // Artifact dir retained (no purge) so the operator can retry.
                $this->failJob($job, 'finalize_exception: ' . $e->getMessage());
            }
            return;
        }

        // Carousel mode enters at `rewritten` (the rewrite seeds /carousel-gen).
        if ($job->status !== RepurposeJobStatus::Rewritten->value) {
            return;
        }

        $rewritten = (array) ($job->rewritten ?? []);
        if (empty($rewritten['title']) || empty($rewritten['body'])) {
            $this->failJob($job, 'finalize_failed: rewritten title/body missing');
            return;
        }

        $job->transitionTo(RepurposeJobStatus::Finalizing, 'finalize_start');

        try {
            $this->finalizeCarousel($job, $rewritten);
        } catch (\Throwable $e) {
            // Artifact dir retained (no purge) so the operator can retry.
            $this->failJob($job, 'finalize_exception: ' . $e->getMessage());
        }
    }

    /**
     * Blog mode (June 13, 2026) — seed a `draft` ContentIdea from the extracted IG
     * material and hand off to the proper Content Engine pipeline. We deliberately
     * do NOT write a generated_article here: the old internal rewrite produced
     * low-quality blogs with no scoring. The operator clicks "Start Research" in
     * /admin/content-engine to run article-prep → write → 5-gate score → images →
     * publish (which then auto-fires the event-driven carousel + cross-post).
     */
    private function finalizeBlog(RepurposeJob $job): void
    {
        $extracted = (array) ($job->extracted ?? []);
        $caption = trim((string) ($extracted['caption'] ?? ''));
        $slides = $this->extractedSlideLines($extracted);

        // Need at least a caption or slide substance to build a usable brief.
        if ($caption === '' && $slides === []) {
            $this->failJob($job, 'finalize_failed: extracted material empty (no caption/slides)');
            return;
        }

        $idea = ContentIdea::create([
            'title' => $this->deriveBlogTitle($caption, $slides),
            'status' => 'draft',
            // `source` is an enum column — 'instagram' is the closest allowed
            // value; the precise provenance lives in source_data.source below.
            'source' => 'instagram',
            'pillar' => $this->resolvePillar($job),
            'auto_mode' => false,
            'instructions' => $this->buildBlogBrief($job, $caption, $slides, $extracted),
            // Manual IG curation = high intent. Seed a virality_score ABOVE the
            // LinkedIn scan gate (linkedin_virality_min_score, default 60) so the
            // post-publish carousel + cross-post fan-out actually fires —
            // ScanBlogForLinkedInConversion's virality gate would otherwise skip a
            // null-score idea, silently breaking the "Blog + Carousel" promise.
            'virality_score' => self::REPURPOSE_VIRALITY_SCORE,
            'virality_breakdown' => ['source' => 'ig_repurpose', 'note' => 'manual curation default'],
            // pub_date drives the Content Engine "Published" column — stamp the
            // ingest time so the row shows a date instead of "—".
            'source_data' => [
                'source' => 'ig_repurpose',
                'url' => $job->source_url,
                'pub_date' => now()->toIso8601String(),
            ],
        ]);

        $job->transitionTo(
            RepurposeJobStatus::Drafted,
            'finalize_blog',
            ['content_idea_id' => $idea->id, 'last_error' => null]
        );
        $this->purge($job);
        // Blog skips the internal claim-correction step now (Content Engine
        // research re-verifies), so there is no corrected-claim count to report.
        app(TelegramNotificationService::class)->sendRepurposeDrafted($job, null, 0);
    }

    /** Substantive slide text lines (skip pure image descriptions / short labels). */
    private function extractedSlideLines(array $extracted): array
    {
        return collect((array) ($extracted['slides'] ?? []))
            ->map(fn ($s) => is_array($s) ? (string) ($s['text'] ?? $s['header'] ?? '') : (string) $s)
            ->map(fn ($t) => trim((string) $t))
            ->filter(fn ($t) => strlen($t) > 25)
            ->values()
            ->all();
    }

    /**
     * Provisional title — article-prep refines the final one. First sentence of the
     * caption, else the first substantive slide line.
     */
    private function deriveBlogTitle(string $caption, array $slides): string
    {
        $seed = $caption !== '' ? $caption : (string) ($slides[0] ?? '');
        $seed = (string) preg_replace('/^(BREAKING|UPDATE|NEWS)\b[:\-\s]*/i', '', $seed);
        $seed = trim((string) Str::of($seed)->before("\n"));
        $sentence = trim((string) Str::of($seed)->before('. '));
        $title = trim((string) Str::limit($sentence !== '' ? $sentence : $seed, 120, ''));

        return $title !== '' ? $title : 'Repurpose IG — ' . trim((string) Str::limit($caption, 60, ''));
    }

    /** Compose the Content Engine research brief from the IG source material. */
    private function buildBlogBrief(RepurposeJob $job, string $caption, array $slides, array $extracted): string
    {
        $narr = $extracted['narrative'] ?? null;
        $narr = is_string($narr) ? trim($narr) : trim((string) json_encode($narr));
        $slidesText = implode("\n- ", $slides);
        $claims = collect((array) ($extracted['claims'] ?? []))
            // Only pull human-readable claim text — never json_encode a malformed
            // claim object into the operator-facing brief (it'd leak raw JSON).
            ->map(fn ($c) => is_array($c) ? (string) ($c['claim'] ?? $c['text'] ?? '') : (string) $c)
            ->map(fn ($t) => trim((string) $t))
            ->filter(fn ($t) => strlen($t) > 8)
            ->take(40)
            ->implode("\n- ");

        $parts = ["SUMBER: Repurpose dari Instagram carousel ({$job->source_url})."];
        if ($caption !== '') {
            $parts[] = "=== Caption sumber ===\n{$caption}";
        }
        if ($narr !== '' && !in_array($narr, ['""', 'null', '[]', '{}'], true)) {
            $parts[] = "=== Ringkasan narasi ===\n{$narr}";
        }
        if ($slidesText !== '') {
            $parts[] = "=== Poin-poin slide sumber ===\n- {$slidesText}";
        }
        if ($claims !== '') {
            $parts[] = "=== Klaim yang HARUS di-fact-check ulang ===\n- {$claims}";
        }
        $parts[] = 'INSTRUKSI: Tulis artikel mendalam berbahasa Indonesia bergaya Ali '
            . '(analisis tajam, bukan sekadar berita). Verifikasi ulang setiap klaim ke '
            . 'sumber kredibel + sitasi. JANGAN menyalin caption mentah.';

        return implode("\n\n", $parts);
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

    /**
     * video_rebrand — ship the composited 4:5 MP4 carousel. It publishes to Instagram
     * + Threads via Zernio (never LinkedIn), but we ALSO create a `video_carousel`
     * LinkedInPost anchor so the job appears in the Content Calendar (LinkedIn tab) and
     * leaves Social Studio. The anchor is display-only — guarded out of every LinkedIn
     * publisher (LinkedInPost::scopeExcludeVideoCarousel) and NEVER runs /linkedin-gen.
     * Composited slides are RETAINED (manual-download UI streams composited_path);
     * `repurpose:reap` clears them on the publish-anchored retention.
     */
    private function finalizeVideoRebrand(RepurposeJob $job): void
    {
        $done = $job->videoSlides()->where('composited_status', 'done')->whereNotNull('composited_path')->count();
        if ($done === 0) {
            $this->failJob($job, 'finalize_video_failed: no composited slides to ship');
            return;
        }

        // Build the post caption with a real follow/comment/save ask (#3). No
        // comment→DM promise — there is no auto-DM infra (CLAUDE.md decision).
        $caption = $this->buildVideoCaption($job);

        // The display-only video_carousel anchor (Post + manual_review LinkedInPost)
        // is created by the shared factory — the SAME path the Zernio schedule/
        // publish flow self-heals through for pre-feature jobs. It sets
        // linkedin_post_id + anchor_post_id on the job; we only transition status.
        $anchor = app(VideoCarouselAnchorService::class)->ensureFor($job, $caption);

        $job->transitionTo(RepurposeJobStatus::Drafted, 'finalize_video', [
            'last_error' => null,
            'rewritten' => array_merge((array) $job->rewritten, ['caption' => $caption]),
        ]);
        app(TelegramNotificationService::class)->sendRepurposeDrafted($job, $anchor->id, $this->correctedClaims($job));
    }

    /**
     * Compose the manual-post caption from the surviving tool titles + a standard
     * Follow/Save/Comment ask. Deliberately omits any comment→DM auto-delivery
     * promise (no auto-DM infra).
     */
    private function buildVideoCaption(RepurposeJob $job): string
    {
        $titles = $job->videoSlides()
            ->where('role', RepurposeVideoSlide::ROLE_TOOL)
            ->orderBy('slide_index')
            ->pluck('header_title')
            ->filter()
            // Collapse any embedded newlines from vision extraction so they don't
            // break the caption's Follow/Save/Comment block formatting.
            ->map(fn ($t) => trim((string) preg_replace('/\s+/', ' ', (string) $t)))
            ->filter()
            ->values();

        $handle = CreatorHandle::resolve();
        $intro = $titles->isNotEmpty()
            ? $titles->implode(', ') . ' — the tools worth your time. 🛠️'
            : 'AI tools worth your time. 🛠️';

        $ask = "Follow {$handle} for more AI tools & workflows.\n"
            . "Save this so you don't lose it.\n"
            . 'Comment "AI" if this was useful.';

        return $intro . "\n\n" . $ask;
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
