<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IG-repurpose first-comment suppression (June 11, 2026).
 *
 * Repurpose carousels anchor an UNPUBLISHED Post purely to author slides — its
 * /blog/{slug} URL 404s, so LinkedInPost::ensureLinkCommentHasUrl() must NOT
 * add a blog link and must clear any stale one (otherwise the first comment
 * posts a dead 404 link). The discriminator is RepurposeJob::isRepurposePost.
 */
class LinkedInRepurposeLinkCommentTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $slug): Post
    {
        return Post::create([
            'category_id' => Category::create(['name' => 'AI & Tech ' . $slug])->id,
            'slug' => $slug,
            'title' => 't',
            'content' => 'c',
        ]);
    }

    public function test_repurpose_draft_clears_stale_blog_url(): void
    {
        config(['app.url' => 'https://alisadikinma.com']);
        $post = $this->makePost('ig-repurpose-anchor');
        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'link_comment' => 'Full article: https://alisadikinma.com/blog/ig-repurpose-anchor',
        ]);
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id]);

        $changed = $draft->ensureLinkCommentHasUrl();

        $this->assertTrue($changed);
        $this->assertSame('', (string) $draft->fresh()->link_comment);
    }

    public function test_repurpose_draft_leaves_empty_link_comment_empty(): void
    {
        config(['app.url' => 'https://alisadikinma.com']);
        $post = $this->makePost('ig-anchor-2');
        $draft = LinkedInPost::factory()->create(['post_id' => $post->id, 'link_comment' => '']);
        RepurposeJob::factory()->create(['anchor_post_id' => $post->id]);

        $changed = $draft->ensureLinkCommentHasUrl();

        $this->assertFalse($changed);
        $this->assertSame('', (string) $draft->fresh()->link_comment);
    }

    public function test_repurpose_draft_isRepurpose_true(): void
    {
        $post = $this->makePost('ig-anchor-3');
        $draft = LinkedInPost::factory()->create(['post_id' => $post->id]);
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id]);

        $this->assertTrue($draft->isRepurpose());
    }
}
