<?php

namespace Tests\Feature;

use App\Jobs\CaptureInstagramPost;
use App\Jobs\ExtractSlideContent;
use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\InstagramCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Phase B — capture job. With InstagramCaptureService mocked (no real browser
 * in CI): success advances FSM capturing → captured + dispatches
 * ExtractSlideContent; 0-slide/failure routes to failed + a Telegram reply
 * (never silent). Idempotency + exception paths covered.
 */
class CaptureInstagramPostJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    private function mockCapture(array $return): void
    {
        $this->mock(InstagramCaptureService::class, function ($m) use ($return) {
            $m->shouldReceive('capture')->once()->andReturn($return);
        });
    }

    public function test_successful_capture_advances_to_captured_and_dispatches_extract(): void
    {
        Bus::fake([ExtractSlideContent::class]);
        $this->mockCapture([
            'success' => true,
            'count' => 3,
            'slides' => ['slide-01.jpg', 'slide-02.jpg', 'slide-03.jpg'],
            'caption' => 'original caption',
            'error' => null,
        ]);

        $job = RepurposeJob::factory()->create(['status' => 'capturing', 'mode' => 'carousel']);

        (new CaptureInstagramPost($job->id))->handle();

        $this->assertSame('captured', $job->refresh()->status);
        Bus::assertDispatched(ExtractSlideContent::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_zero_slides_routes_to_failed_with_telegram_reply(): void
    {
        Bus::fake([ExtractSlideContent::class]);
        $this->mockCapture([
            'success' => false,
            'count' => 0,
            'slides' => [],
            'caption' => '',
            'error' => 'login_wall',
        ]);

        $job = RepurposeJob::factory()->create(['status' => 'capturing', 'mode' => 'blog']);

        (new CaptureInstagramPost($job->id))->handle();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('login_wall', (string) $job->last_error);
        Bus::assertNotDispatched(ExtractSlideContent::class);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage'));
    }

    public function test_service_exception_routes_to_failed(): void
    {
        Bus::fake([ExtractSlideContent::class]);
        $this->mock(InstagramCaptureService::class, function ($m) {
            $m->shouldReceive('capture')->once()->andThrow(new \RuntimeException('boom'));
        });

        $job = RepurposeJob::factory()->create(['status' => 'capturing', 'mode' => 'carousel']);

        (new CaptureInstagramPost($job->id))->handle();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('capture_exception', (string) $job->last_error);
        Bus::assertNotDispatched(ExtractSlideContent::class);
    }

    public function test_non_capturing_status_is_noop(): void
    {
        Bus::fake([ExtractSlideContent::class]);
        // Service must NOT be called when the job isn't in capturing.
        $this->mock(InstagramCaptureService::class, function ($m) {
            $m->shouldNotReceive('capture');
        });

        $job = RepurposeJob::factory()->create(['status' => 'captured', 'mode' => 'carousel']);

        (new CaptureInstagramPost($job->id))->handle();

        $this->assertSame('captured', $job->refresh()->status);
        Bus::assertNotDispatched(ExtractSlideContent::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
