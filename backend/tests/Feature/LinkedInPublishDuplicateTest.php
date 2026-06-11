<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInAccount;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\LinkedInPublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression for the draft-148 incident (2026-06-11): when LinkedIn rejects a
 * (re)publish with HTTP 422 "Content is a duplicate of urn:li:share:...", the
 * named share IS the live post — a prior attempt created it but its response
 * was lost. The publish must be treated as an idempotent SUCCESS so the draft
 * is marked Published + the cross-post fan-out still fires, instead of being
 * stranded live-on-LinkedIn-but-FAILED with nothing in Publer.
 */
class LinkedInPublishDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'linkedin.oauth.client_id' => 'test-client',
            'linkedin.oauth.client_secret' => 'test-secret',
            'linkedin.api.base_url' => 'https://api.linkedin.com/v2',
        ]);
        Queue::fake();
    }

    private function makeConnectedAccount(): LinkedInAccount
    {
        return LinkedInAccount::create([
            'person_urn' => 'urn:li:person:testperson',
            'display_name' => 'Test Account',
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'access_token_expires_at' => now()->addDays(30),
        ]);
    }

    private function makeTextDraft(): LinkedInPost
    {
        $cat = Category::create(['name' => 'T', 'slug' => 'c-' . Str::random(5)]);
        $post = Post::create([
            'category_id' => $cat->id,
            'title' => 'P-' . Str::random(5),
            'content' => 'Body',
            'slug' => 'p-' . Str::random(8),
            'published' => true,
            'published_at' => now(),
            // no featured_image → publishText skips the thumbnail upload and
            // makes exactly one POST to /ugcPosts (the one we fake to 422).
        ]);

        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'text',
            'content' => 'This exact content already exists on LinkedIn.',
            'link_comment' => 'Full article: https://alisadikinma.com/blog/x',
        ]);
    }

    public function test_duplicate_422_is_treated_as_idempotent_success(): void
    {
        $this->makeConnectedAccount();
        $draft = $this->makeTextDraft();

        Http::fake([
            '*/ugcPosts*' => Http::response([
                'errorDetailType' => 'com.linkedin.common.error.BadRequest',
                'message' => 'com.linkedin.content.common.exception.BadRequestResponseException: Content is a duplicate of urn:li:share:7470701611313471488',
                'status' => 422,
            ], 422),
        ]);

        $result = app(LinkedInPublishService::class)->publish($draft);

        $this->assertTrue($result['success']);
        $this->assertSame('urn:li:share:7470701611313471488', $result['post_urn']);
        $this->assertStringContainsString('7470701611313471488', (string) $result['post_url']);
        $this->assertNull($result['error']);
    }

    public function test_non_duplicate_422_remains_a_failure(): void
    {
        $this->makeConnectedAccount();
        $draft = $this->makeTextDraft();

        Http::fake([
            '*/ugcPosts*' => Http::response([
                'message' => 'Some other validation error',
                'status' => 422,
            ], 422),
        ]);

        $result = app(LinkedInPublishService::class)->publish($draft);

        $this->assertFalse($result['success']);
        $this->assertNull($result['post_urn']);
        $this->assertStringContainsString('422', (string) $result['error']);
    }
}
