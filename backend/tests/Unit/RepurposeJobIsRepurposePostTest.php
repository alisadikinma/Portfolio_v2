<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RepurposeJob::isRepurposePost — single source of truth deciding whether a
 * Post / LinkedIn draft originates from an IG-repurpose job. Drives blog
 * first-comment suppression (repurpose carousels anchor an UNPUBLISHED Post,
 * so its /blog/{slug} URL 404s — no platform should link it).
 */
class RepurposeJobIsRepurposePostTest extends TestCase
{
    use RefreshDatabase;

    public function test_false_when_both_ids_null(): void
    {
        $this->assertFalse(RepurposeJob::isRepurposePost(null, null));
    }

    public function test_true_when_linkedin_post_id_linked(): void
    {
        $post = Post::factory()->create(['category_id' => Category::create(['name' => 'AI & Tech'])->id]);
        $draft = LinkedInPost::factory()->create(['post_id' => $post->id]);
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id]);

        $this->assertTrue(RepurposeJob::isRepurposePost($draft->post_id, $draft->id));
    }

    public function test_true_when_anchor_post_id_linked(): void
    {
        $post = Post::factory()->create(['category_id' => Category::create(['name' => 'AI & Tech'])->id]);
        RepurposeJob::factory()->create(['anchor_post_id' => $post->id]);

        $this->assertTrue(RepurposeJob::isRepurposePost($post->id, null));
    }

    public function test_true_when_content_idea_source_instagram(): void
    {
        $post = Post::factory()->create(['category_id' => Category::create(['name' => 'AI & Tech'])->id]);
        ContentIdea::create([
            'title' => 'Repurposed from IG',
            'status' => 'completed',
            'source' => 'instagram',
            'pillar' => 'general',
            'result_post_id' => $post->id,
        ]);

        $this->assertTrue(RepurposeJob::isRepurposePost($post->id, null));
    }

    public function test_false_for_ordinary_blog_draft(): void
    {
        $post = Post::factory()->create(['category_id' => Category::create(['name' => 'AI & Tech'])->id]);
        $draft = LinkedInPost::factory()->create(['post_id' => $post->id]);

        $this->assertFalse(RepurposeJob::isRepurposePost($draft->post_id, $draft->id));
    }
}
