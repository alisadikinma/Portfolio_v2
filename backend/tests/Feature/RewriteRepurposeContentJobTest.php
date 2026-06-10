<?php

namespace Tests\Feature;

use App\Jobs\FinalizeRepurpose;
use App\Jobs\RewriteRepurposeContent;
use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\RepurposeRewriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Phase E — rewrite job. RepurposeRewriteService mocked: success persists
 * `rewritten` + advances researched → rewritten + dispatches FinalizeRepurpose;
 * failure → failed + Telegram reply. Idempotent.
 */
class RewriteRepurposeContentJobTest extends TestCase
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

    public function test_success_persists_rewritten_and_dispatches_finalize(): void
    {
        Bus::fake([FinalizeRepurpose::class]);
        $rewritten = [
            'title' => 'The Real Story Behind AI Job Claims',
            'body' => '<h2>Intro</h2><p>...</p>',
            'excerpt' => 'A sharper, fact-checked take.',
            'meta_keywords' => 'AI, jobs, automation',
            'sources_appendix' => ['https://example.org/study'],
        ];
        $this->mock(RepurposeRewriteService::class, function ($m) use ($rewritten) {
            $m->shouldReceive('rewrite')->once()->andReturn(['success' => true, 'rewritten' => $rewritten, 'error' => null]);
        });

        $job = RepurposeJob::factory()->create(['status' => 'researched', 'mode' => 'blog', 'research' => ['verdicts' => [['claim' => 'x']]]]);

        (new RewriteRepurposeContent($job->id))->handle();

        $job->refresh();
        $this->assertSame('rewritten', $job->status);
        $this->assertSame($rewritten['title'], $job->rewritten['title']);
        Bus::assertDispatched(FinalizeRepurpose::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_failure_routes_to_failed_with_reply(): void
    {
        Bus::fake([FinalizeRepurpose::class]);
        $this->mock(RepurposeRewriteService::class, function ($m) {
            $m->shouldReceive('rewrite')->once()->andReturn(['success' => false, 'rewritten' => null, 'error' => 'rewrite_unparseable']);
        });

        $job = RepurposeJob::factory()->create(['status' => 'researched', 'mode' => 'carousel', 'research' => ['verdicts' => [['claim' => 'x']]]]);

        (new RewriteRepurposeContent($job->id))->handle();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('rewrite_unparseable', (string) $job->last_error);
        Bus::assertNotDispatched(FinalizeRepurpose::class);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage'));
    }

    public function test_non_researched_status_is_noop(): void
    {
        Bus::fake([FinalizeRepurpose::class]);
        $this->mock(RepurposeRewriteService::class, function ($m) {
            $m->shouldNotReceive('rewrite');
        });

        $job = RepurposeJob::factory()->create(['status' => 'rewritten', 'mode' => 'blog']);

        (new RewriteRepurposeContent($job->id))->handle();

        $this->assertSame('rewritten', $job->refresh()->status);
        Bus::assertNotDispatched(FinalizeRepurpose::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
