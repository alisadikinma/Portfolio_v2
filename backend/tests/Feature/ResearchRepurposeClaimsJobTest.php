<?php

namespace Tests\Feature;

use App\Jobs\ResearchRepurposeClaims;
use App\Jobs\RewriteRepurposeContent;
use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\RepurposeResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Phase D — fact-check job. RepurposeResearchService mocked: success persists
 * `research` (verdicts+sources) + advances extracted → researched + dispatches
 * RewriteRepurposeContent; failure → failed + Telegram reply. Idempotent.
 */
class ResearchRepurposeClaimsJobTest extends TestCase
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

    public function test_success_persists_research_and_dispatches_rewrite(): void
    {
        Bus::fake([RewriteRepurposeContent::class]);
        $research = [
            'verdicts' => [
                ['claim' => 'AI replaces all jobs by 2025', 'status' => 'wrong', 'corrected' => 'AI augments most jobs; broad replacement is not supported by evidence.', 'sources' => ['https://example.org/study']],
            ],
            'summary' => 'Corrected one overstated claim.',
            'corrected_count' => 1,
        ];
        $this->mock(RepurposeResearchService::class, function ($m) use ($research) {
            $m->shouldReceive('research')->once()->andReturn(['success' => true, 'research' => $research, 'error' => null]);
        });

        $job = RepurposeJob::factory()->create(['status' => 'extracted', 'mode' => 'blog', 'extracted' => ['claims' => ['x']]]);

        (new ResearchRepurposeClaims($job->id))->handle();

        $job->refresh();
        $this->assertSame('researched', $job->status);
        $this->assertSame(1, $job->research['corrected_count']);
        Bus::assertDispatched(RewriteRepurposeContent::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_failure_routes_to_failed_with_reply(): void
    {
        Bus::fake([RewriteRepurposeContent::class]);
        $this->mock(RepurposeResearchService::class, function ($m) {
            $m->shouldReceive('research')->once()->andReturn(['success' => false, 'research' => null, 'error' => 'research_unparseable']);
        });

        $job = RepurposeJob::factory()->create(['status' => 'extracted', 'mode' => 'blog', 'extracted' => ['claims' => ['x']]]);

        (new ResearchRepurposeClaims($job->id))->handle();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('research_unparseable', (string) $job->last_error);
        Bus::assertNotDispatched(RewriteRepurposeContent::class);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage'));
    }

    public function test_non_extracted_status_is_noop(): void
    {
        Bus::fake([RewriteRepurposeContent::class]);
        $this->mock(RepurposeResearchService::class, function ($m) {
            $m->shouldNotReceive('research');
        });

        $job = RepurposeJob::factory()->create(['status' => 'researched', 'mode' => 'blog']);

        (new ResearchRepurposeClaims($job->id))->handle();

        $this->assertSame('researched', $job->refresh()->status);
        Bus::assertNotDispatched(RewriteRepurposeContent::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
