<?php

namespace Tests\Feature;

use App\Jobs\ExtractSlideContent;
use App\Jobs\FinalizeRepurpose;
use App\Jobs\ResearchRepurposeClaims;
use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\SlideVisionExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Phase C — vision-extract job. SlideVisionExtractor mocked (no real CLI in CI):
 * success persists `extracted` + advances captured → extracted, then branches on
 * mode (carousel → ResearchRepurposeClaims, blog → FinalizeRepurpose); failure →
 * failed + Telegram reply. Idempotent.
 */
class ExtractSlideContentJobTest extends TestCase
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

    public function test_carousel_success_persists_extracted_and_dispatches_research(): void
    {
        Bus::fake([ResearchRepurposeClaims::class, FinalizeRepurpose::class]);
        $extracted = [
            'slides' => [['n' => 1, 'text' => 'Hook', 'role' => 'hook']],
            'caption' => 'cap',
            'claims' => ['AI replaces all jobs by 2025'],
            'narrative' => 'A bold claim about AI.',
        ];
        $this->mock(SlideVisionExtractor::class, function ($m) use ($extracted) {
            $m->shouldReceive('extract')->once()->andReturn(['success' => true, 'extracted' => $extracted, 'error' => null]);
        });

        $job = RepurposeJob::factory()->create(['status' => 'captured', 'mode' => 'carousel', 'slides_path' => 'repurpose/1']);

        (new ExtractSlideContent($job->id))->handle();

        $job->refresh();
        $this->assertSame('extracted', $job->status);
        $this->assertSame($extracted['claims'], $job->extracted['claims']);
        Bus::assertDispatched(ResearchRepurposeClaims::class, fn ($j) => $j->repurposeJobId === $job->id);
        Bus::assertNotDispatched(FinalizeRepurpose::class);
    }

    public function test_blog_success_skips_research_and_dispatches_finalize(): void
    {
        Bus::fake([ResearchRepurposeClaims::class, FinalizeRepurpose::class]);
        $extracted = [
            'slides' => [['n' => 1, 'text' => 'Hook', 'role' => 'hook']],
            'caption' => 'cap',
            'claims' => ['AI replaces all jobs by 2025'],
            'narrative' => 'A bold claim about AI.',
        ];
        $this->mock(SlideVisionExtractor::class, function ($m) use ($extracted) {
            $m->shouldReceive('extract')->once()->andReturn(['success' => true, 'extracted' => $extracted, 'error' => null]);
        });

        $job = RepurposeJob::factory()->create(['status' => 'captured', 'mode' => 'blog', 'slides_path' => 'repurpose/1']);

        (new ExtractSlideContent($job->id))->handle();

        $this->assertSame('extracted', $job->refresh()->status);
        // Blog skips the internal research+rewrite — straight to finalize (draft idea).
        Bus::assertDispatched(FinalizeRepurpose::class, fn ($j) => $j->repurposeJobId === $job->id);
        Bus::assertNotDispatched(ResearchRepurposeClaims::class);
    }

    public function test_failure_routes_to_failed_with_reply(): void
    {
        Bus::fake([ResearchRepurposeClaims::class]);
        $this->mock(SlideVisionExtractor::class, function ($m) {
            $m->shouldReceive('extract')->once()->andReturn(['success' => false, 'extracted' => null, 'error' => 'vision_unparseable']);
        });

        $job = RepurposeJob::factory()->create(['status' => 'captured', 'mode' => 'blog', 'slides_path' => 'repurpose/1']);

        (new ExtractSlideContent($job->id))->handle();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('vision_unparseable', (string) $job->last_error);
        Bus::assertNotDispatched(ResearchRepurposeClaims::class);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage'));
    }

    public function test_non_captured_status_is_noop(): void
    {
        Bus::fake([ResearchRepurposeClaims::class]);
        $this->mock(SlideVisionExtractor::class, function ($m) {
            $m->shouldNotReceive('extract');
        });

        $job = RepurposeJob::factory()->create(['status' => 'extracted', 'mode' => 'blog']);

        (new ExtractSlideContent($job->id))->handle();

        $this->assertSame('extracted', $job->refresh()->status);
        Bus::assertNotDispatched(ResearchRepurposeClaims::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
