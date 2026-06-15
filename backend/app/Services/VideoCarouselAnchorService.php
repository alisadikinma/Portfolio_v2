<?php

namespace App\Services;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Idempotently materializes the video_carousel calendar anchor (a LinkedInPost)
 * for a video_rebrand repurpose job.
 *
 * A video_rebrand job has NO inherent LinkedIn output (LinkedIn has no video-
 * carousel format) — the anchor is a display-only row so the job shows on the
 * Content Calendar (LinkedIn tab) and leaves Social Studio, while Zernio does the
 * real IG/Threads publish. FinalizeRepurpose creates it at finalize time; the
 * Zernio schedule/publish path calls ensureFor() too so a job finalized BEFORE
 * this feature shipped (no anchor) self-heals onto the calendar when scheduled,
 * instead of the schedule silently reaching Zernio but never the calendar
 * (production: job 26 scheduled to Zernio but never appeared on the grid).
 */
class VideoCarouselAnchorService
{
    /**
     * Return the existing anchor, or create + link a minimal one. Never throws on
     * a present anchor (idempotent). Does NOT transition the job's status — the
     * caller (finalize / mirror) owns that.
     */
    public function ensureFor(RepurposeJob $job, ?string $caption = null): LinkedInPost
    {
        $existing = $job->videoAnchor();
        if ($existing !== null) {
            return $existing;
        }

        $caption = ($caption !== null && trim($caption) !== '')
            ? $caption
            : ($job->rewritten['caption'] ?? $job->igCaption());

        /** @var array{0:int,1:LinkedInPost} $created */
        $created = DB::transaction(function () use ($job, $caption) {
            $title = $job->displayTopic();
            $slug = (Str::slug($title) ?: 'repurpose-video').'-'.Str::lower(Str::random(6));

            // post_id is NOT NULL on linkedin_posts → mirror finalizeCarousel: a
            // minimal UNPUBLISHED Post (its /blog/{slug} 404s — isRepurposePost keys
            // off anchor_post_id, so no platform emits a "Full article" comment).
            $postData = [
                'category_id' => $this->resolveCategoryId(),
                'slug' => $slug,
                'published' => false,
                'published_at' => null,
            ];
            foreach (['title' => $title, 'excerpt' => '', 'content' => $caption] as $col => $val) {
                if (Schema::hasColumn('posts', $col)) {
                    $postData[$col] = $val;
                }
            }
            $post = Post::create($postData);

            // Translation so the calendar/queue shows a real title (prod posts keep
            // title in post_translations, not on the posts table).
            $post->translations()->create([
                'language' => 'id',
                'title' => $title,
                'slug' => $slug,
                'excerpt' => '',
                'content' => $caption,
                'meta_keywords' => '',
            ]);

            $anchor = LinkedInPost::create([
                'post_id' => $post->id,
                'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
                'content' => $caption,
                'hashtags' => [], // NOT NULL json
                'status' => 'manual_review',
            ]);

            return [$post->id, $anchor];
        });

        [$postId, $anchor] = $created;
        $job->update(['anchor_post_id' => $postId, 'linkedin_post_id' => $anchor->id]);
        $job->setRelation('linkedinPost', $anchor); // fresh for the caller's immediate use

        return $anchor;
    }

    private function resolveCategoryId(): int
    {
        $id = Category::query()->value('id');

        return $id !== null ? (int) $id : (int) Category::create(['name' => 'Repurpose'])->id;
    }
}
