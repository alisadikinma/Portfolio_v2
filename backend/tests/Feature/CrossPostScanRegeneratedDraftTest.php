<?php

namespace Tests\Feature;

use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression — idempotency guard must be keyed on linkedin_post_id, NOT post_id.
 *
 * Reproduces the production bug hit on drafts 144/145 and 146/147: when a draft
 * is regenerated (e.g. text→carousel), the old draft is soft-deleted and a NEW
 * linkedin_posts row is created for the SAME blog post. The old draft's
 * IG/TikTok/Threads/FB siblings carry the same post_id, so a post_id-keyed
 * guard saw them as a "live row" and disqualified the new draft — cross-post
 * captions never generated for it.
 *
 * @see ScanLinkedInForCrossPost::hasLive*Row()
 */
class CrossPostScanRegeneratedDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // fan-out dispatches Generate*Post caption-gen jobs
    }

    /** Mirrors ScanLinkedInForCrossPostTest — posts.category_id is NOT NULL. */
    private function makePost(): int
    {
        $categoryId = DB::table('categories')->value('id')
            ?? DB::table('categories')->insertGetId([
                'name' => 'General',
                'slug' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('posts')->insertGetId([
            'category_id' => $categoryId,
            'title' => 'Regen scan test post',
            'slug' => 'regen-scan-' . uniqid(),
            'content' => 'Body for regen scan test.',
            'published' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function doneSlides(): array
    {
        return [
            ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'done', 'image_url' => 'https://x/1.png', 'image_job_uuid' => 'u1'],
            ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'done', 'image_url' => 'https://x/2.png', 'image_job_uuid' => 'u2'],
        ];
    }

    public function test_regenerated_draft_fans_out_despite_old_drafts_siblings(): void
    {
        $postId = $this->makePost();

        // Old draft (regenerated away from) + its full sibling set, all keyed
        // to the same blog post. Old draft soft-deleted, as regenerate does.
        $old = LinkedInPost::factory()->create([
            'post_id' => $postId,
            'format' => 'carousel',
            'status' => 'manual_review',
        ]);
        InstagramPost::create(['linkedin_post_id' => $old->id, 'post_id' => $postId, 'status' => 'pending_generation']);
        TiktokPost::create(['linkedin_post_id' => $old->id, 'post_id' => $postId, 'status' => 'pending_generation']);
        ThreadsPost::create(['linkedin_post_id' => $old->id, 'post_id' => $postId, 'status' => 'pending_generation', 'format' => 'carousel']);
        FacebookPost::create(['linkedin_post_id' => $old->id, 'post_id' => $postId, 'status' => 'pending_generation', 'format' => 'carousel']);
        $old->delete();

        // New live draft for the SAME post, slides rendered.
        $new = LinkedInPost::factory()->create([
            'post_id' => $postId,
            'format' => 'carousel',
            'status' => 'validating',
            'carousel_slides' => $this->doneSlides(),
            'updated_at' => now(),
        ]);

        Artisan::call('social-cross-post:scan', ['--draft-id' => $new->id]);

        // The new draft must get its OWN siblings — the old draft's rows no
        // longer block it.
        $this->assertTrue(InstagramPost::where('linkedin_post_id', $new->id)->exists(), 'IG sibling for new draft');
        $this->assertTrue(TiktokPost::where('linkedin_post_id', $new->id)->exists(), 'TikTok sibling for new draft');
        $this->assertTrue(ThreadsPost::where('linkedin_post_id', $new->id)->exists(), 'Threads sibling for new draft');
        $this->assertTrue(FacebookPost::where('linkedin_post_id', $new->id)->exists(), 'FB sibling for new draft');
    }

    public function test_idempotency_still_holds_for_the_same_draft(): void
    {
        $postId = $this->makePost();
        $draft = LinkedInPost::factory()->create([
            'post_id' => $postId,
            'format' => 'carousel',
            'status' => 'validating',
            'carousel_slides' => $this->doneSlides(),
            'updated_at' => now(),
        ]);

        Artisan::call('social-cross-post:scan', ['--draft-id' => $draft->id]);
        Artisan::call('social-cross-post:scan', ['--draft-id' => $draft->id]);

        // Re-scanning the same draft must not double-create siblings.
        $this->assertSame(1, InstagramPost::where('linkedin_post_id', $draft->id)->count());
        $this->assertSame(1, TiktokPost::where('linkedin_post_id', $draft->id)->count());
    }
}
