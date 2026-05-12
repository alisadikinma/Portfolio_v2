<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Services\LinkedInFormatMixGovernor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LinkedInFormatMixGovernorTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::firstOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_format_carousel_target_ratio'],
            ['value' => '0.8', 'type' => 'text']
        );
        Setting::firstOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_format_lookback_window'],
            ['value' => '10', 'type' => 'text']
        );
        Setting::firstOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_format_governor_enabled'],
            ['value' => 'true', 'type' => 'text']
        );
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

    private function makeDraft(string $format, string $status, ?Carbon $createdAt = null): LinkedInPost
    {
        $createdAt = $createdAt ?? Carbon::now();
        return LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => $format,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function governor(): LinkedInFormatMixGovernor
    {
        return new LinkedInFormatMixGovernor();
    }

    public function test_skip_when_plugin_already_emitted_carousel(): void
    {
        $current = $this->makeDraft('carousel', LinkedInPostStatus::PendingGeneration->value);

        $this->assertFalse($this->governor()->shouldOverrideToCarousel($current, 'carousel'));
    }

    public function test_skip_when_governor_disabled(): void
    {
        Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_format_governor_enabled')
            ->update(['value' => 'false']);

        $current = $this->makeDraft('text', LinkedInPostStatus::PendingGeneration->value);

        // Even if ratio is bad, disabled flag wins
        $this->assertFalse($this->governor()->shouldOverrideToCarousel($current, 'text'));
    }

    public function test_skip_during_bootstrap_when_history_below_lookback(): void
    {
        // Only 3 historical drafts (lookback = 10) — bootstrap window
        for ($i = 0; $i < 3; $i++) {
            $this->makeDraft('text', LinkedInPostStatus::Published->value, Carbon::now()->subHours($i + 1));
        }
        $current = $this->makeDraft('text', LinkedInPostStatus::PendingGeneration->value);

        $this->assertFalse($this->governor()->shouldOverrideToCarousel($current, 'text'));
    }

    public function test_override_to_carousel_when_ratio_below_target(): void
    {
        // 10 historical: 4 carousel + 6 text → ratio 0.4 < 0.8 target → override
        for ($i = 0; $i < 4; $i++) {
            $this->makeDraft('carousel', LinkedInPostStatus::Published->value, Carbon::now()->subHours($i + 10));
        }
        for ($i = 0; $i < 6; $i++) {
            $this->makeDraft('text', LinkedInPostStatus::Published->value, Carbon::now()->subHours($i + 1));
        }
        $current = $this->makeDraft('text', LinkedInPostStatus::PendingGeneration->value);

        $this->assertTrue($this->governor()->shouldOverrideToCarousel($current, 'text'));
    }

    public function test_no_override_when_ratio_already_at_target(): void
    {
        // 10 historical: 8 carousel + 2 text → ratio 0.8 == target → no override
        for ($i = 0; $i < 8; $i++) {
            $this->makeDraft('carousel', LinkedInPostStatus::Published->value, Carbon::now()->subHours($i + 5));
        }
        for ($i = 0; $i < 2; $i++) {
            $this->makeDraft('text', LinkedInPostStatus::Published->value, Carbon::now()->subHours($i + 1));
        }
        $current = $this->makeDraft('text', LinkedInPostStatus::PendingGeneration->value);

        $this->assertFalse($this->governor()->shouldOverrideToCarousel($current, 'text'));
    }

    public function test_excludes_cancelled_and_failed_from_lookback(): void
    {
        // 10 historical: 4 carousel published + 6 text cancelled/failed
        // Cancelled + failed should NOT count → lookback effectively 4 only → bootstrap
        for ($i = 0; $i < 4; $i++) {
            $this->makeDraft('carousel', LinkedInPostStatus::Published->value, Carbon::now()->subHours($i + 10));
        }
        for ($i = 0; $i < 6; $i++) {
            $this->makeDraft('text', LinkedInPostStatus::Cancelled->value, Carbon::now()->subHours($i + 1));
        }
        $current = $this->makeDraft('text', LinkedInPostStatus::PendingGeneration->value);

        // Only 4 active in lookback < window=10 → bootstrap → no override
        $this->assertFalse($this->governor()->shouldOverrideToCarousel($current, 'text'));
    }

    public function test_compute_ratio_returns_carousel_proportion(): void
    {
        // 10 historical: 3 carousel + 7 text → 0.3
        for ($i = 0; $i < 3; $i++) {
            $this->makeDraft('carousel', LinkedInPostStatus::Published->value, Carbon::now()->subHours($i + 10));
        }
        for ($i = 0; $i < 7; $i++) {
            $this->makeDraft('text', LinkedInPostStatus::Published->value, Carbon::now()->subHours($i + 1));
        }
        $current = $this->makeDraft('text', LinkedInPostStatus::PendingGeneration->value);

        $this->assertEqualsWithDelta(0.3, $this->governor()->computeRatio($current), 0.001);
    }
}
