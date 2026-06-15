<?php

namespace Tests\Feature\Admin;

use App\Jobs\PublishViaPubler;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Manual per-platform (re)publish to Publer:
 * POST /admin/linkedin-drafts/{id}/publish-crosspost/{platform}.
 *
 * Recovers a FAILED cross-post sibling without regenerating its caption —
 * resets the row and re-dispatches PublishViaPubler. Gated on a configured
 * Publer account for the platform.
 */
class LinkedInPublishCrossPostTest extends TestCase
{
    use RefreshDatabase;

    private function enableInstagram(): void
    {
        Setting::create(['group' => 'publer', 'key' => 'publer_instagram_account_id', 'value' => 'acc_ig_1', 'type' => 'text']);
        // Pin to Publer — the PublisherResolver now defaults to Zernio (primary),
        // so this Publer-path test must select Publer explicitly.
        Setting::create(['group' => 'zernio', 'key' => 'crosspost_publisher_instagram', 'value' => 'publer', 'type' => 'text']);
    }

    private function carouselWithFailedIg(): LinkedInPost
    {
        $draft = LinkedInPost::factory()->create(['format' => 'carousel', 'status' => 'published']);
        InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'failed',
            'last_error' => 'Publer media job did not complete within 12 polls.',
        ]);

        return $draft;
    }

    public function test_requires_auth(): void
    {
        $this->postJson('/api/admin/linkedin-drafts/1/publish-crosspost/instagram')->assertUnauthorized();
    }

    public function test_republishes_failed_instagram_sibling(): void
    {
        Queue::fake();
        $this->enableInstagram();
        $draft = $this->carouselWithFailedIg();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/linkedin-drafts/{$draft->id}/publish-crosspost/instagram")
            ->assertStatus(202)
            ->assertJson(['success' => true]);

        $sibling = InstagramPost::where('linkedin_post_id', $draft->id)->first();
        $this->assertSame('publishing', $sibling->status);
        $this->assertNull($sibling->last_error);
        $this->assertNull($sibling->publer_post_id);

        Queue::assertPushed(PublishViaPubler::class,
            fn ($j) => $j->platform === 'instagram' && $j->siblingPostId === $sibling->id);
    }

    public function test_422_when_platform_not_configured(): void
    {
        Queue::fake();
        $draft = $this->carouselWithFailedIg(); // no publer setting → disabled

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/linkedin-drafts/{$draft->id}/publish-crosspost/instagram")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'platform_not_configured');

        Queue::assertNothingPushed();
    }

    public function test_404_when_sibling_missing(): void
    {
        Queue::fake();
        $this->enableInstagram();
        $draft = LinkedInPost::factory()->create(['format' => 'carousel', 'status' => 'published']);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/linkedin-drafts/{$draft->id}/publish-crosspost/instagram")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'no_sibling');

        Queue::assertNothingPushed();
    }
}
