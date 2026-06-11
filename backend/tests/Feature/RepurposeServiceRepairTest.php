<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Services\RepurposeResearchService;
use App\Services\RepurposeRewriteService;
use App\Services\SlideVisionExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Phase D — the three repurpose services route through runRepurposeParsed, so a
 * first-attempt unparseable CLI output is recovered by the repair retry. On a
 * terminal failure each service keeps its specific error string.
 *
 * @see docs/plans/2026-06-11-repurpose-llm-hardening.md
 */
class RepurposeServiceRepairTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpSlideDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.repurpose.driver' => 'local']);
        config(['services.repurpose.empty_mcp_config' => '']);
    }

    protected function tearDown(): void
    {
        if ($this->tmpSlideDir !== '' && is_dir($this->tmpSlideDir)) {
            array_map('unlink', glob($this->tmpSlideDir . '/*') ?: []);
            @rmdir($this->tmpSlideDir);
        }
        parent::tearDown();
    }

    public function test_rewrite_recovers_on_repair(): void
    {
        Process::fake(['*' => Process::sequence()
            ->push('{"title":"t","body":')  // truncated
            ->push('{"title":"t","body":"<p>x</p>","excerpt":"e","meta_keywords":"a,b","sources_appendix":[]}')]);

        $job = RepurposeJob::factory()->create([
            'status' => 'researched',
            'mode' => 'blog',
            'extracted' => ['narrative' => 'n', 'slides' => [['text' => 's']], 'claims' => ['x']],
            'research' => ['verdicts' => [['claim' => 'x', 'status' => 'ok']]],
        ]);

        $res = app(RepurposeRewriteService::class)->rewrite($job);

        $this->assertTrue($res['success']);
        $this->assertSame('t', $res['rewritten']['title']);
    }

    public function test_rewrite_still_fails_after_repair_keeps_error_string(): void
    {
        Process::fake(['*' => Process::sequence()->push('garbage')->push('still garbage')]);

        $job = RepurposeJob::factory()->create([
            'status' => 'researched',
            'mode' => 'carousel',
            'extracted' => ['claims' => ['x']],
            'research' => ['verdicts' => [['claim' => 'x']]],
        ]);

        $res = app(RepurposeRewriteService::class)->rewrite($job);

        $this->assertFalse($res['success']);
        $this->assertSame('rewrite_unparseable', $res['error']);
    }

    public function test_research_recovers_on_repair(): void
    {
        Process::fake(['*' => Process::sequence()
            ->push('{"verdicts":')  // truncated
            ->push('{"verdicts":[{"claim":"x","status":"ok","corrected":"x","sources":[]}],"summary":"s"}')]);

        $job = RepurposeJob::factory()->create([
            'status' => 'extracted',
            'extracted' => ['narrative' => 'n', 'claims' => ['x']],
        ]);

        $res = app(RepurposeResearchService::class)->research($job);

        $this->assertTrue($res['success']);
        $this->assertCount(1, $res['research']['verdicts']);
    }

    public function test_vision_recovers_on_repair(): void
    {
        $this->tmpSlideDir = storage_path('app/repurpose/test-' . uniqid());
        @mkdir($this->tmpSlideDir, 0775, true);
        file_put_contents($this->tmpSlideDir . '/slide-01.jpg', 'fakejpgbytes');
        file_put_contents($this->tmpSlideDir . '/caption.txt', 'a caption');

        Process::fake(['*' => Process::sequence()
            ->push('{"claims":')  // truncated
            ->push('{"claims":["c"],"slides":[],"caption":"a caption","narrative":"n"}')]);

        $job = RepurposeJob::factory()->create([
            'status' => 'captured',
            'slides_path' => 'repurpose/' . basename($this->tmpSlideDir),
        ]);

        $res = app(SlideVisionExtractor::class)->extract($job);

        $this->assertTrue($res['success']);
        $this->assertSame(['c'], $res['extracted']['claims']);
    }
}
