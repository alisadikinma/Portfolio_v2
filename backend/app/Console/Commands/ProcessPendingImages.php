<?php

namespace App\Console\Commands;

use App\Models\ImageGenerationJob;
use App\Services\ImageGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessPendingImages extends Command
{
    protected $signature = 'blog:process-images';
    protected $description = 'Poll GeminiGen API for pending image jobs and download completed ones';

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
                    $this->warn("    HTTP {$response->status()} — skipping");
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
                    $job->update([
                        'status' => 'failed',
                        'error_message' => $data['error_message'] ?? 'Generation failed',
                    ]);
                    $this->error("    FAILED: " . ($data['error_message'] ?? 'unknown'));

                    // Sync failure to content_ideas
                    $this->syncToContentIdea($job, null, true);

                } else {
                    $this->line("    Still processing (status={$status})");
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
        // Includes images_ready because late webhook/polling after retries must still sync
        $ideas = \App\Models\ContentIdea::whereIn('status', ['article_ready', 'generating_images', 'images_ready'])
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

                $allResolved = collect($prompts)->every(fn ($p) => in_array($p['status'] ?? '', ['done', 'failed']));
                $anyDone = collect($prompts)->contains(fn ($p) => ($p['status'] ?? '') === 'done');
                if ($allResolved && $anyDone && $idea->status === 'generating_images') {
                    if ($idea->auto_mode) {
                        $idea->status = 'completed';
                        $this->info("    All images resolved + auto_mode — idea #{$idea->id} → completed");
                    } else {
                        $idea->status = 'images_ready';
                        $this->info("    All images resolved — idea #{$idea->id} → images_ready");
                    }
                }

                $idea->save();
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
