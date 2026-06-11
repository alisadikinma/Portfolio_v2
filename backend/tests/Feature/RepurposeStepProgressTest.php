<?php

namespace Tests\Feature;

use App\Jobs\CaptureInstagramPost;
use App\Jobs\ExtractSlideContent;
use App\Jobs\ResearchRepurposeClaims;
use App\Jobs\RewriteRepurposeContent;
use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\InstagramCaptureService;
use App\Services\RepurposeResearchService;
use App\Services\RepurposeRewriteService;
use App\Services\SlideVisionExtractor;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase B — each pipeline step job emits exactly one progress bubble before its
 * success FSM advance, and the advance still happens even when the notify throws
 * (best-effort; never blocks the pipeline).
 */
class RepurposeStepProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);

        Bus::fake();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    private function assertProgressSent(string $needle): void
    {
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage')
            && str_contains((string) $req['text'], $needle));
    }

    // ---- Capture ----

    public function test_capture_emits_slide_count_bubble(): void
    {
        $this->mock(InstagramCaptureService::class)
            ->shouldReceive('capture')->once()->andReturn(['success' => true, 'count' => 8]);

        $job = RepurposeJob::factory()->create(['status' => 'capturing', 'mode' => 'carousel']);
        (new CaptureInstagramPost($job->id))->handle();

        $this->assertSame('captured', $job->refresh()->status);
        $this->assertProgressSent('8 slide ke-capture');
    }

    public function test_capture_advances_even_when_progress_throws(): void
    {
        $this->mock(InstagramCaptureService::class)
            ->shouldReceive('capture')->once()->andReturn(['success' => true, 'count' => 8]);
        $this->mock(TelegramNotificationService::class)
            ->shouldReceive('sendRepurposeProgress')->andThrow(new \RuntimeException('boom'));

        $job = RepurposeJob::factory()->create(['status' => 'capturing', 'mode' => 'carousel']);
        (new CaptureInstagramPost($job->id))->handle();

        $this->assertSame('captured', $job->refresh()->status);
    }

    // ---- Extract ----

    public function test_extract_emits_claims_count_bubble(): void
    {
        $this->mock(SlideVisionExtractor::class)
            ->shouldReceive('extract')->once()->andReturn([
                'success' => true,
                'extracted' => ['slides' => [], 'caption' => '', 'narrative' => '', 'claims' => ['a', 'b', 'c']],
            ]);

        $job = RepurposeJob::factory()->create(['status' => 'captured', 'mode' => 'carousel']);
        (new ExtractSlideContent($job->id))->handle();

        $this->assertSame('extracted', $job->refresh()->status);
        $this->assertProgressSent('3 klaim ditemukan');
    }

    public function test_extract_advances_even_when_progress_throws(): void
    {
        $this->mock(SlideVisionExtractor::class)
            ->shouldReceive('extract')->once()->andReturn([
                'success' => true,
                'extracted' => ['claims' => ['a', 'b']],
            ]);
        $this->mock(TelegramNotificationService::class)
            ->shouldReceive('sendRepurposeProgress')->andThrow(new \RuntimeException('boom'));

        $job = RepurposeJob::factory()->create(['status' => 'captured', 'mode' => 'carousel']);
        (new ExtractSlideContent($job->id))->handle();

        $this->assertSame('extracted', $job->refresh()->status);
    }

    // ---- Research ----

    public function test_research_emits_corrected_count_bubble(): void
    {
        $this->mock(RepurposeResearchService::class)
            ->shouldReceive('research')->once()->andReturn([
                'success' => true,
                'research' => ['verdicts' => [], 'corrected_count' => 2],
            ]);

        $job = RepurposeJob::factory()->create(['status' => 'extracted', 'mode' => 'carousel']);
        (new ResearchRepurposeClaims($job->id))->handle();

        $this->assertSame('researched', $job->refresh()->status);
        $this->assertProgressSent('2 klaim dikoreksi');
    }

    public function test_research_advances_even_when_progress_throws(): void
    {
        $this->mock(RepurposeResearchService::class)
            ->shouldReceive('research')->once()->andReturn([
                'success' => true,
                'research' => ['corrected_count' => 0],
            ]);
        $this->mock(TelegramNotificationService::class)
            ->shouldReceive('sendRepurposeProgress')->andThrow(new \RuntimeException('boom'));

        $job = RepurposeJob::factory()->create(['status' => 'extracted', 'mode' => 'carousel']);
        (new ResearchRepurposeClaims($job->id))->handle();

        $this->assertSame('researched', $job->refresh()->status);
    }

    // ---- Rewrite ----

    public function test_rewrite_emits_finalizing_bubble(): void
    {
        $this->mock(RepurposeRewriteService::class)
            ->shouldReceive('rewrite')->once()->andReturn([
                'success' => true,
                'rewritten' => ['title' => 'X', 'body' => '<p>Y</p>'],
            ]);

        $job = RepurposeJob::factory()->create(['status' => 'researched', 'mode' => 'carousel']);
        (new RewriteRepurposeContent($job->id))->handle();

        $this->assertSame('rewritten', $job->refresh()->status);
        $this->assertProgressSent('ditulis ulang');
    }

    public function test_rewrite_advances_even_when_progress_throws(): void
    {
        $this->mock(RepurposeRewriteService::class)
            ->shouldReceive('rewrite')->once()->andReturn([
                'success' => true,
                'rewritten' => ['title' => 'X', 'body' => '<p>Y</p>'],
            ]);
        $this->mock(TelegramNotificationService::class)
            ->shouldReceive('sendRepurposeProgress')->andThrow(new \RuntimeException('boom'));

        $job = RepurposeJob::factory()->create(['status' => 'researched', 'mode' => 'carousel']);
        (new RewriteRepurposeContent($job->id))->handle();

        $this->assertSame('rewritten', $job->refresh()->status);
    }
}
