<?php

namespace Tests\Unit;

use App\Jobs\PublishViaPubler;
use App\Jobs\PublishViaZernio;
use App\Models\Setting;
use App\Support\PublisherResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase G — PublisherResolver: per-platform selector (zernio|publer),
 * publisher-aware enabled gate, published-id column, and dispatch routing.
 */
class PublisherResolverTest extends TestCase
{
    use RefreshDatabase;

    private function selector(string $platform, string $value): void
    {
        Setting::updateOrCreate(
            ['group' => 'zernio', 'key' => "crosspost_publisher_{$platform}"],
            ['value' => $value, 'type' => 'text']
        );
    }

    public function test_defaults_to_zernio_for_the_three_platforms(): void
    {
        $this->assertSame('zernio', PublisherResolver::for('instagram'));
        $this->assertSame('zernio', PublisherResolver::for('tiktok'));
        $this->assertSame('zernio', PublisherResolver::for('threads'));
    }

    public function test_returns_publer_when_selector_flipped(): void
    {
        $this->selector('instagram', 'publer');
        $this->assertSame('publer', PublisherResolver::for('instagram'));
    }

    public function test_unknown_platform_falls_back_to_publer(): void
    {
        // A platform with no Zernio path (e.g. pinterest) → publer.
        $this->assertSame('publer', PublisherResolver::for('pinterest'));
    }

    public function test_reddit_facebook_youtube_default_to_zernio(): void
    {
        // 2026-06-16 — these joined ZERNIO_PLATFORMS. With no explicit selector
        // row the resolver defaults to zernio (the seeder sets reddit='off' /
        // fb,yt='zernio' separately — see ZernioSettingsSeederTest).
        $this->assertSame('zernio', PublisherResolver::for('reddit'));
        $this->assertSame('zernio', PublisherResolver::for('facebook'));
        $this->assertSame('zernio', PublisherResolver::for('youtube'));
    }

    public function test_off_selector_disables_and_never_dispatches(): void
    {
        Queue::fake();
        $this->selector('reddit', 'off');
        // Even with an account id present, an 'off' platform is hard-disabled.
        Setting::create(['group' => 'zernio', 'key' => 'zernio_reddit_account_id', 'value' => 'rd_acc']);

        $this->assertSame('off', PublisherResolver::for('reddit'));
        $this->assertFalse(PublisherResolver::isPlatformEnabled('reddit'));

        PublisherResolver::dispatchPublish('reddit', 99);
        Queue::assertNothingPushed();
    }

    public function test_facebook_dispatches_to_zernio_by_default(): void
    {
        Queue::fake();
        PublisherResolver::dispatchPublish('facebook', 5);
        Queue::assertPushed(PublishViaZernio::class, fn ($j) => $j->platform === 'facebook' && $j->siblingPostId === 5);
        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_published_id_column_is_publisher_aware(): void
    {
        $this->assertSame('zernio_post_id', PublisherResolver::publishedIdColumn('instagram'));
        $this->selector('tiktok', 'publer');
        $this->assertSame('publer_post_id', PublisherResolver::publishedIdColumn('tiktok'));
    }

    public function test_is_platform_enabled_delegates_to_selected_publisher(): void
    {
        // Selector=zernio but no zernio account id → disabled.
        $this->assertFalse(PublisherResolver::isPlatformEnabled('instagram'));

        // Add zernio account id → enabled under the zernio selector.
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'ig_acc']);
        $this->assertTrue(PublisherResolver::isPlatformEnabled('instagram'));

        // Flip to publer with no publer account id → disabled again.
        $this->selector('instagram', 'publer');
        $this->assertFalse(PublisherResolver::isPlatformEnabled('instagram'));

        // Add publer account id → enabled under the publer selector.
        Setting::create(['group' => 'publer', 'key' => 'publer_instagram_account_id', 'value' => 'ig_pub']);
        $this->assertTrue(PublisherResolver::isPlatformEnabled('instagram'));
    }

    public function test_dispatch_routes_to_zernio_by_default(): void
    {
        Queue::fake();
        PublisherResolver::dispatchPublish('instagram', 42);
        Queue::assertPushed(PublishViaZernio::class, fn ($j) => $j->platform === 'instagram' && $j->siblingPostId === 42);
        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_dispatch_routes_to_publer_when_flipped(): void
    {
        Queue::fake();
        $this->selector('threads', 'publer');
        PublisherResolver::dispatchPublish('threads', 7);
        Queue::assertPushed(PublishViaPubler::class, fn ($j) => $j->platform === 'threads' && $j->siblingPostId === 7);
        Queue::assertNotPushed(PublishViaZernio::class);
    }
}
