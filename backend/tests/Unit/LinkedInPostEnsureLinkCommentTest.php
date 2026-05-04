<?php

namespace Tests\Unit;

use App\Models\LinkedInPost;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Defense-in-depth guard for legacy drafts created before commit c64e9c31
 * (Apr 29, 2026) — without this, a non-URL link_comment (e.g., a stale
 * pull_quote) gets posted as the first comment in place of the blog URL.
 */
class LinkedInPostEnsureLinkCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_false_when_link_comment_already_has_url(): void
    {
        config(['app.url' => 'https://example.com']);
        $post = Post::create(['slug' => 'foo', 'title' => 't', 'content' => 'c']);
        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'link_comment' => 'Full article: https://example.com/blog/foo',
        ]);

        $changed = $draft->ensureLinkCommentHasUrl();

        $this->assertFalse($changed);
        $this->assertSame('Full article: https://example.com/blog/foo', $draft->fresh()->link_comment);
    }

    public function test_rewrites_legacy_non_url_link_comment(): void
    {
        config(['app.url' => 'https://alisadikinma.com']);
        $post = Post::create(['slug' => 'vibe-coding-surveillance', 'title' => 't', 'content' => 'c']);
        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'link_comment' => 'Infrastruktur pengawasan massal bisa dibangun tanpa konsekuensi.',
        ]);

        $changed = $draft->ensureLinkCommentHasUrl();

        $this->assertTrue($changed);
        $this->assertSame(
            'Full article: https://alisadikinma.com/blog/vibe-coding-surveillance',
            $draft->fresh()->link_comment
        );
    }

    public function test_rewrites_empty_link_comment(): void
    {
        config(['app.url' => 'https://alisadikinma.com']);
        $post = Post::create(['slug' => 'hello-world', 'title' => 't', 'content' => 'c']);
        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'link_comment' => '',
        ]);

        $changed = $draft->ensureLinkCommentHasUrl();

        $this->assertTrue($changed);
        $this->assertSame('Full article: https://alisadikinma.com/blog/hello-world', $draft->fresh()->link_comment);
    }

    public function test_returns_false_when_post_slug_missing(): void
    {
        config(['app.url' => 'https://alisadikinma.com']);
        $draft = LinkedInPost::factory()->create([
            'post_id' => 999999, // dangling FK -> post relation null
            'link_comment' => 'some random sentence without url',
        ]);

        $changed = $draft->ensureLinkCommentHasUrl();

        $this->assertFalse($changed);
        $this->assertSame('some random sentence without url', $draft->fresh()->link_comment);
    }

    public function test_returns_false_when_app_url_empty(): void
    {
        config(['app.url' => '']);
        $post = Post::create(['slug' => 'x', 'title' => 't', 'content' => 'c']);
        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'link_comment' => 'no url here',
        ]);

        $changed = $draft->ensureLinkCommentHasUrl();

        $this->assertFalse($changed);
    }

    public function test_recognises_http_lowercase_and_uppercase(): void
    {
        config(['app.url' => 'https://example.com']);
        $post = Post::create(['slug' => 'p', 'title' => 't', 'content' => 'c']);
        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'link_comment' => 'See HTTPS://other.com/here',
        ]);

        $changed = $draft->ensureLinkCommentHasUrl();

        $this->assertFalse($changed);
        $this->assertSame('See HTTPS://other.com/here', $draft->fresh()->link_comment);
    }
}
