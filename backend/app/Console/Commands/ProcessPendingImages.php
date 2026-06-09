<?php

namespace App\Console\Commands;

use App\Enums\ContentIdeaStatus;
use App\Models\ImageGenerationJob;
use App\Services\ImageGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessPendingImages extends Command
{
    protected $signature = 'blog:process-images';
    protected $description = 'Poll GeminiGen API for pending image jobs and download completed ones';

    /**
     * Jobs that have been 'processing' longer than this are considered
     * stuck — GeminiGen either failed internally without firing the
     * webhook, or is wedged in a queue state that will never resolve.
     * We treat them as failed and drive handleSegmentFailure so the UI
     * unsticks and auto-retry can fire. Must be longer than GeminiGen's
     * typical generation time (~30-90s) but short enough that operators
     * don't wait forever on a dead job.
     */
    private const MAX_JOB_AGE_MINUTES = 10;

    public function handle(ImageGenerationService $imageService): int
    {
        $pending = ImageGenerationJob::where('status', 'processing')->get();

        if ($pending->isEmpty()) {
            $this->info('No pending image jobs.');
            return 0;
        }

        $this->info("Found {$pending->count()} pending image jobs. Checking...");
        $apiKey = config('services.geminigen.api_key');

        foreach ($pending as $job) {
            $this->line("  Checking UUID: {$job->uuid} (type={$job->type})...");

            try {
                $response = Http::timeout(15)
                    ->withHeaders(['x-api-key' => $apiKey])
                    ->get("https://api.geminigen.ai/uapi/v1/history/{$job->uuid}");

                if (!$response->successful()) {
                    // Transient HTTP error: skip this tick BUT still enforce
                    // MAX_JOB_AGE so GeminiGen being down persistently
                    // doesn't let stuck jobs pile up forever.
                    $ageMinutes = $job->created_at
                        ? (int) $job->created_at->diffInMinutes(now())
                        : 0;
                    if ($ageMinutes >= self::MAX_JOB_AGE_MINUTES) {
                        $reason = "GeminiGen HTTP {$response->status()} after {$ageMinutes}min — treating as failed";
                        $job->update(['status' => 'failed', 'error_message' => $reason]);
                        $this->warn("    TIMEOUT: {$reason}");
                        if ($job->type === 'carousel_slide') {
                            app(\App\Services\LinkedInCarouselImageService::class)
                                ->handleWebhook($job->uuid, 'IMAGE_GENERATION_FAILED', ['error_message' => $reason]);
                        } else {
                            $this->syncToContentIdea($job, null, true);
                            $imageService->handleSegmentFailure($job, $reason);
                        }
                    } else {
                        $this->warn("    HTTP {$response->status()} (age={$ageMinutes}min) — skipping this tick");
                    }
                    continue;
                }

                $data = $response->json();
                $status = (int) ($data['status'] ?? 0);

                // Image URL can be in different locations depending on the API response
                // Priority: image_url (R2 signed, works) > file_download_url (needs extra auth) > thumbnail
                $remoteUrl = $data['generate_result']
                    ?? $data['media_url']
                    ?? ($data['generated_image'][0]['image_url'] ?? null)
                    ?? ($data['generated_image'][0]['file_download_url'] ?? null)
                    ?? ($data['thumbnail_url'] ?? null);

                if ($status === 2 && $remoteUrl) {
                    $this->info("    Image ready! Downloading...");

                    // LinkedIn carousel slide jobs: delegate to the dedicated
                    // service which handles correct storage path
                    // (linkedin-carousel/ + planned_filename), updates the job
                    // row, and mirrors image_status onto LinkedInPost
                    // .carousel_slides JSON. Mirrors the early-routing in
                    // ImageGenerationService::handleWebhook so the polling
                    // path and webhook path stay symmetric.
                    if ($job->type === 'carousel_slide') {
                        app(\App\Services\LinkedInCarouselImageService::class)
                            ->handleWebhook($job->uuid, 'IMAGE_GENERATION_COMPLETED', ['media_url' => $remoteUrl]);
                        $this->info("    Carousel slide mirrored for draft {$job->linkedin_post_id} slide {$job->slide_index}");
                        continue;
                    }

                    $localUrl = $imageService->downloadAndStore($remoteUrl);

                    $job->update([
                        'status' => 'completed',
                        'image_url' => $localUrl,
                        'remote_url' => $remoteUrl,
                    ]);

                    // Apply to post (if linked to a blog post)
                    if ($job->post_id) {
                        if ($job->type === 'hero' && $localUrl) {
                            $job->post->update(['featured_image' => $localUrl]);
                            $this->info("    Hero image set on post {$job->post_id}");
                        } elseif ($job->type === 'inline' && $localUrl) {
                            $this->insertInlineImage($job);
                            $this->info("    Inline image inserted into post {$job->post_id}");
                        }
                    }

                    // Sync to content_ideas (Content Engine pipeline)
                    $this->syncToContentIdea($job, $localUrl);

                } elseif ($status === 3) {
                    $reason = $data['error_message'] ?? 'Generation failed';
                    $job->update([
                        'status' => 'failed',
                        'error_message' => $reason,
                    ]);
                    $this->error("    FAILED: " . $reason);

                    // LinkedIn carousel slide failure: mirror status to slide
                    // JSON so the UI flips from generating → failed and the
                    // operator can retry. ContentIdea sync only matters for
                    // article jobs.
                    if ($job->type === 'carousel_slide') {
                        app(\App\Services\LinkedInCarouselImageService::class)
                            ->handleWebhook($job->uuid, 'IMAGE_GENERATION_FAILED', ['error_message' => $reason]);
                        continue;
                    }

                    // Sync failure to content_ideas (advance-rule update + variation mirror)
                    $this->syncToContentIdea($job, null, true);
                    // Drive the segment retry state machine: bump retry_count,
                    // append failure_history, auto-schedule retry or mark terminal.
                    $imageService->handleSegmentFailure($job, $reason);

                } else {
                    // Age-based stuck detection: GeminiGen sometimes fails
                    // internally without firing the webhook AND without
                    // flipping history.status to 3. Symptom: job stays
                    // 'processing' forever, UI never unsticks. If the job
                    // was created > MAX_JOB_AGE_MINUTES ago and GeminiGen
                    // still reports status != 2/3, we treat it as failed
                    // and drive the segment retry state machine.
                    $ageMinutes = $job->created_at
                        ? (int) $job->created_at->diffInMinutes(now())
                        : 0;
                    if ($ageMinutes >= self::MAX_JOB_AGE_MINUTES) {
                        $reason = "Stuck in 'processing' for {$ageMinutes} min — GeminiGen never resolved (status={$status})";
                        $job->update([
                            'status' => 'failed',
                            'error_message' => $reason,
                        ]);
                        $this->warn("    TIMEOUT: {$reason}");

                        // LinkedIn carousel slide stuck-timeout: mirror failed
                        // status onto the slide JSON so the UI flips to failed.
                        if ($job->type === 'carousel_slide') {
                            app(\App\Services\LinkedInCarouselImageService::class)
                                ->handleWebhook($job->uuid, 'IMAGE_GENERATION_FAILED', ['error_message' => $reason]);
                        } else {
                            $this->syncToContentIdea($job, null, true);
                            $imageService->handleSegmentFailure($job, $reason);
                        }
                    } else {
                        $this->line("    Still processing (status={$status}, age={$ageMinutes}min)");
                    }
                }

            } catch (\Exception $e) {
                $this->warn("    Error: {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return 0;
    }

    /**
     * Sync completed/failed image back to content_ideas.generated_article.image_prompts[]
     */
    private function syncToContentIdea(ImageGenerationJob $job, ?string $imageUrl, bool $failed = false): void
    {
        // Includes images_ready + completed because late webhook/polling after
        // retries must still sync the variations[] mirror — operators routinely
        // dispatch additional variations after the first one auto-advances the
        // idea to images_ready/completed, and those late results must reach
        // the admin UI even though the FSM has already moved on. Mirror-only
        // update — never reverses the FSM transition.
        $ideas = \App\Models\ContentIdea::whereIn('status', ['article_ready', 'generating_images', 'images_ready', 'completed'])
            ->whereNotNull('generated_article')
            ->get();

        $status = $failed ? 'failed' : 'done';

        foreach ($ideas as $idea) {
            $article = $idea->generated_article;
            $prompts = $article['image_prompts'] ?? [];
            $updated = false;
            $matchedSegment = null;

            foreach ($prompts as $i => $prompt) {
                // Variations[] match first (new multi-choice schema)
                $variations = $prompt['variations'] ?? [];
                foreach ($variations as $vi => $v) {
                    if (($v['job_uuid'] ?? null) === $job->uuid) {
                        $prompts[$i]['variations'][$vi]['status'] = $status;
                        if ($imageUrl) {
                            $prompts[$i]['variations'][$vi]['url'] = $imageUrl;
                        }
                        // Mirror flat fields for backward compatibility
                        if ($imageUrl) {
                            $prompts[$i]['generated_url'] = $imageUrl;
                        }
                        // Segment status reflects aggregate variation state
                        $anyGenerating = collect($prompts[$i]['variations'])->contains(fn ($vv) => ($vv['status'] ?? '') === 'generating');
                        $prompts[$i]['status'] = $anyGenerating ? 'generating' : $status;
                        $updated = true;
                        $matchedSegment = $i;
                        break 2;
                    }
                }

                // Legacy flat job_uuid fallback
                if (($prompt['job_uuid'] ?? null) === $job->uuid) {
                    $prompts[$i]['status'] = $status;
                    if ($imageUrl) {
                        $prompts[$i]['generated_url'] = $imageUrl;
                    }
                    $updated = true;
                    $matchedSegment = $i;
                    break;
                }
            }

            if ($updated) {
                $article['image_prompts'] = $prompts;
                $idea->generated_article = $article;

                // Hard image-completion gate (GEO publish-and-forget fix): a
                // 'failed' segment — even a terminally-failed one — HOLDS the
                // idea at generating_images. We never compile/publish a blog with
                // a broken image; the operator retries/skips first. Only
                // all-done-or-skipped + ≥1 done advances. Shared predicate so the
                // poller and the webhook handler can never diverge.
                $canAdvance = \App\Services\ImageGenerationService::segmentsResolvedForAdvance($prompts);

                // Cover-critical block: a skipped cover (no cover image) must
                // also HOLD, even though 'skipped' is otherwise advance-eligible.
                $cover = $prompts[0] ?? null;
                $coverCritical = $cover !== null
                    && ($cover['status'] ?? '') === 'skipped';

                if ($canAdvance && !$coverCritical && $idea->status === 'generating_images') {
                    // Persist generated_article first (so the transitionTo
                    // update doesn't lose the in-memory mutation), then
                    // transition status via FSM.
                    $idea->save();
                    if ($idea->auto_mode) {
                        $idea->transitionTo(ContentIdeaStatus::Completed, 'process_pending_images_all_done');
                        $this->info("    All images resolved + auto_mode — idea #{$idea->id} → completed");
                    } else {
                        $idea->transitionTo(ContentIdeaStatus::ImagesReady, 'process_pending_images_all_done');
                        $this->info("    All images resolved — idea #{$idea->id} → images_ready");
                    }
                } elseif ($coverCritical && $idea->status === 'generating_images') {
                    $this->warn("    Cover segment terminal — idea #{$idea->id} blocked at generating_images");
                    $idea->save();
                } else {
                    $idea->save();
                }
                $this->info("    Synced to content idea #{$idea->id}, segment {$matchedSegment}");
                return;
            }
        }
    }

    private function insertInlineImage(ImageGenerationJob $job): void
    {
        if (!$job->image_url || !$job->insert_after_heading) return;

        $imgTag = '<figure class="my-8"><img src="' . e($job->image_url) . '" alt="' . e(\Illuminate\Support\Str::limit($job->prompt, 100)) . '" class="w-full rounded-xl" loading="lazy" /><figcaption class="text-sm text-gray-500 mt-2 text-center">' . e(\Illuminate\Support\Str::limit($job->insert_after_heading, 80)) . '</figcaption></figure>';

        foreach ($job->post->translations as $translation) {
            $content = $translation->content;
            $headingPos = stripos($content, $job->insert_after_heading);
            if ($headingPos !== false) {
                $closeTagPos = strpos($content, '</h2>', $headingPos);
                if ($closeTagPos !== false) {
                    $insertPos = $closeTagPos + 5;
                    $translation->content = substr($content, 0, $insertPos) . "\n" . $imgTag . "\n" . substr($content, $insertPos);
                    $translation->save();
                }
            }
        }
    }
}
