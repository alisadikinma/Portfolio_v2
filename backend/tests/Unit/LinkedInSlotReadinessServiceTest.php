<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\LinkedInSlotReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the atomic readiness check performed by social:publish-slot
 * orchestrator before firing publishes at a slot tick.
 *
 * Text format: trivially ready (no image deps). FB + TH siblings publish
 * independently per their own readiness — never block LinkedIn.
 *
 * Carousel format: ALL slides must have image_status=done AND IG+TT+TH
 * siblings (when present) must have non-empty caption + non-failed status.
 */
class LinkedInSlotReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test-' . Str::random(4)]);
    }

    private function makePost(): Post
    {
        return Post::create([
            'category_id' => $this->category->id,
            'title' => 'P-' . Str::random(6),
            'content' => 'Body',
            'slug' => 'p-' . Str::random(8),
            'published' => true,
            'published_at' => now(),
        ]);
    }

    private function makeTextDraft(): LinkedInPost
    {
        return LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'text',
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'carousel_slides' => null,
        ]);
    }

    private function makeCarouselDraft(array $slideImageStatuses): LinkedInPost
    {
        $slides = [];
        foreach ($slideImageStatuses as $i => $status) {
            $slides[] = [
                'slide_number' => $i + 1,
                'layout_hint' => $i === 0 ? 'cover' : 'body',
                'copy' => "Slide " . ($i + 1),
                'image_prompt' => 'prompt',
                'image_status' => $status,
                'image_url' => $status === 'done' ? "https://example.com/slide-{$i}.png" : null,
            ];
        }
        return LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'carousel',
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'carousel_slides' => $slides,
        ]);
    }

    private function service(): LinkedInSlotReadinessService
    {
        return new LinkedInSlotReadinessService();
    }

    public function test_text_format_is_always_ready(): void
    {
        $draft = $this->makeTextDraft();
        $result = $this->service()->isReady($draft);

        $this->assertTrue($result['ready']);
        $this->assertEmpty($result['blockers']);
    }

    public function test_carousel_with_all_slides_done_and_no_siblings_is_ready(): void
    {
        $draft = $this->makeCarouselDraft(['done', 'done', 'done']);
        $result = $this->service()->isReady($draft);

        $this->assertTrue($result['ready']);
    }

    public function test_carousel_with_pending_slide_blocks(): void
    {
        $draft = $this->makeCarouselDraft(['done', 'pending', 'done']);
        $result = $this->service()->isReady($draft);

        $this->assertFalse($result['ready']);
        $this->assertNotEmpty($result['blockers']);
        $this->assertStringContainsString('slide_2', $result['blockers'][0]);
    }

    public function test_carousel_with_failed_slide_blocks(): void
    {
        $draft = $this->makeCarouselDraft(['done', 'failed']);
        $result = $this->service()->isReady($draft);

        $this->assertFalse($result['ready']);
        $this->assertNotEmpty($result['blockers']);
    }

    public function test_carousel_with_empty_caption_sibling_blocks(): void
    {
        $draft = $this->makeCarouselDraft(['done', 'done']);
        InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'caption' => '', // empty — blocker
        ]);

        $result = $this->service()->isReady($draft);

        $this->assertFalse($result['ready']);
        $this->assertContains('instagram_caption_empty', $result['blockers']);
    }

    public function test_carousel_with_all_ready_siblings_passes(): void
    {
        $draft = $this->makeCarouselDraft(['done', 'done', 'done']);
        InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'caption' => 'IG caption here',
        ]);
        TiktokPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'caption' => 'TT caption here',
            'title' => 'TT title',
        ]);
        ThreadsPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'caption' => 'TH caption here',
        ]);

        $result = $this->service()->isReady($draft);

        $this->assertTrue($result['ready']);
    }

    public function test_carousel_with_failed_sibling_blocks(): void
    {
        $draft = $this->makeCarouselDraft(['done', 'done']);
        InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'failed',
            'caption' => 'IG caption',
        ]);

        $result = $this->service()->isReady($draft);

        $this->assertFalse($result['ready']);
        $this->assertContains('instagram_status_failed', $result['blockers']);
    }
}
