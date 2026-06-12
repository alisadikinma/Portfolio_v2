<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\LinkedInSlotReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the Approve / Schedule-for-later caption gate (June 12, 2026).
 *
 * Stricter than isReady(): a MISSING sibling on an enabled platform blocks
 * (caption never generated), an unconfigured platform is exempt, and a
 * cancelled sibling is exempt (operator opted out).
 */
class LinkedInCaptionReadinessForApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test-' . Str::random(4)]);
    }

    private function enablePlatform(string $platform): void
    {
        Setting::create([
            'group' => 'publer',
            'key' => "publer_{$platform}_account_id",
            'value' => 'acct-' . Str::random(6),
        ]);
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

    private function makeCarousel(): LinkedInPost
    {
        return LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'carousel',
            'status' => LinkedInPostStatus::ManualReview->value,
            'carousel_slides' => [
                ['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://e/1.png'],
            ],
        ]);
    }

    private function service(): LinkedInSlotReadinessService
    {
        return new LinkedInSlotReadinessService();
    }

    private function makeSibling(string $class, LinkedInPost $draft, string $status, string $caption): void
    {
        $attrs = [
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => $status,
            'caption' => $caption,
        ];
        if ($class === TiktokPost::class) {
            $attrs['title'] = 'TT title';
        }
        $class::create($attrs);
    }

    public function test_text_format_is_caption_ready(): void
    {
        $draft = LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'text',
            'status' => LinkedInPostStatus::ManualReview->value,
            'carousel_slides' => null,
        ]);

        $result = $this->service()->captionReadinessForApproval($draft);

        $this->assertTrue($result['ready']);
        $this->assertEmpty($result['blockers']);
    }

    public function test_missing_sibling_on_enabled_platform_blocks(): void
    {
        $this->enablePlatform('instagram');
        $this->enablePlatform('tiktok');
        $this->enablePlatform('threads');
        $draft = $this->makeCarousel();

        $result = $this->service()->captionReadinessForApproval($draft);

        $this->assertFalse($result['ready']);
        $this->assertContains('instagram_caption_missing', $result['blockers']);
        $this->assertContains('tiktok_caption_missing', $result['blockers']);
        $this->assertContains('threads_caption_missing', $result['blockers']);
    }

    public function test_disabled_platform_missing_does_not_block(): void
    {
        // No publer_*_account_id settings → all platforms unconfigured → exempt.
        $draft = $this->makeCarousel();

        $result = $this->service()->captionReadinessForApproval($draft);

        $this->assertTrue($result['ready']);
        $this->assertEmpty($result['blockers']);
    }

    public function test_empty_caption_blocks(): void
    {
        $this->enablePlatform('instagram');
        $draft = $this->makeCarousel();
        $this->makeSibling(InstagramPost::class, $draft, 'awaiting_review', '');

        $result = $this->service()->captionReadinessForApproval($draft);

        $this->assertFalse($result['ready']);
        $this->assertContains('instagram_caption_empty', $result['blockers']);
    }

    public function test_in_progress_caption_blocks(): void
    {
        $this->enablePlatform('tiktok');
        $draft = $this->makeCarousel();
        $this->makeSibling(TiktokPost::class, $draft, 'generating', 'partial');

        $result = $this->service()->captionReadinessForApproval($draft);

        $this->assertFalse($result['ready']);
        $this->assertContains('tiktok_generating', $result['blockers']);
    }

    public function test_failed_caption_blocks(): void
    {
        $this->enablePlatform('threads');
        $draft = $this->makeCarousel();
        $this->makeSibling(ThreadsPost::class, $draft, 'failed', 'some caption');

        $result = $this->service()->captionReadinessForApproval($draft);

        $this->assertFalse($result['ready']);
        $this->assertContains('threads_failed', $result['blockers']);
    }

    public function test_cancelled_sibling_is_exempt(): void
    {
        $this->enablePlatform('instagram');
        $draft = $this->makeCarousel();
        // Operator deliberately cancelled IG — should not block approval.
        $this->makeSibling(InstagramPost::class, $draft, 'cancelled', '');

        $result = $this->service()->captionReadinessForApproval($draft);

        $this->assertTrue($result['ready']);
        $this->assertEmpty($result['blockers']);
    }

    public function test_all_settled_captions_pass(): void
    {
        $this->enablePlatform('instagram');
        $this->enablePlatform('tiktok');
        $this->enablePlatform('threads');
        $draft = $this->makeCarousel();
        $this->makeSibling(InstagramPost::class, $draft, 'awaiting_review', 'IG caption');
        $this->makeSibling(TiktokPost::class, $draft, 'published', 'TT caption');
        $this->makeSibling(ThreadsPost::class, $draft, 'publishing', 'TH caption');

        $result = $this->service()->captionReadinessForApproval($draft);

        $this->assertTrue($result['ready']);
        $this->assertEmpty($result['blockers']);
    }
}
