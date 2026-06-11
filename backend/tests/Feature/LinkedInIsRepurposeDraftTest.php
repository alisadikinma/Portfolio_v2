<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Services\LinkedInGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B — isRepurposeDraft() predicate. The carousel-gen router uses this to
 * drop the foreshadow beat (--narrative=free) for IG-repurpose drafts while
 * normal blog carousels keep --narrative=5act. Detection covers both repurpose
 * modes: carousel (RepurposeJob.linkedin_post_id / anchor_post_id) and blog
 * (the draft's post links a ContentIdea with source='instagram').
 *
 * @see docs/plans/2026-06-11-carousel-sketchnote-infographic.md
 */
class LinkedInIsRepurposeDraftTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LinkedInGenerationService
    {
        return app(LinkedInGenerationService::class);
    }

    /** Post requires a category_id (NOT NULL); make one + a carousel draft on it. */
    private function makeDraft(): array
    {
        $cat = Category::create(['name' => 'AI & Tech']);
        $post = Post::factory()->create([
            'category_id' => $cat->id,
            'title' => 'Anchor Post',
            'content' => '<p>body</p>',
        ]);
        $draft = LinkedInPost::factory()->create(['post_id' => $post->id, 'format' => 'carousel']);

        return [$post, $draft];
    }

    public function test_true_when_repurpose_job_references_the_draft(): void
    {
        [$post, $draft] = $this->makeDraft();

        RepurposeJob::factory()->create([
            'mode' => 'carousel',
            'status' => 'drafted',
            'linkedin_post_id' => $draft->id,
            'anchor_post_id' => $post->id,
        ]);

        $this->assertTrue($this->service()->isRepurposeDraft($draft));
    }

    public function test_true_when_only_anchor_post_id_matches(): void
    {
        [$post, $draft] = $this->makeDraft();

        // RepurposeJob NOT linked to this draft's id — only its anchor post.
        // Pins the anchor_post_id OR-branch independently of linkedin_post_id.
        RepurposeJob::factory()->create([
            'mode' => 'carousel',
            'status' => 'drafted',
            'linkedin_post_id' => null,
            'anchor_post_id' => $post->id,
        ]);

        $this->assertTrue($this->service()->isRepurposeDraft($draft));
    }

    public function test_true_when_draft_post_links_instagram_content_idea(): void
    {
        [$post, $draft] = $this->makeDraft();

        ContentIdea::create([
            'title' => 'Repurposed IG idea',
            'result_post_id' => $post->id,
            'source' => 'instagram',
        ]);

        $this->assertTrue($this->service()->isRepurposeDraft($draft));
    }

    public function test_false_for_plain_blog_carousel_draft(): void
    {
        [$post, $draft] = $this->makeDraft();

        // A normal Content Engine idea (not instagram) must NOT count.
        ContentIdea::create([
            'title' => 'Normal trending idea',
            'result_post_id' => $post->id,
            'source' => 'manual',
        ]);

        $this->assertFalse($this->service()->isRepurposeDraft($draft));
    }
}
