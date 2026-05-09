<?php

namespace Tests\Unit;

use App\Services\PipelineGuard;
use App\Services\TiktokGenerationService;
use Mockery;
use Tests\TestCase;

/**
 * Regression coverage for TiktokGenerationService::parseOrchestratorOutput.
 *
 * Parser is the same balanced-brace scanner inherited from
 * BaseSocialGenerationService — a small smoke-test confirms the inheritance
 * works without re-testing every edge case (those are covered exhaustively
 * by InstagramGenerationServiceParseTest).
 */
class TiktokGenerationServiceParseTest extends TestCase
{
    private function svc(): TiktokGenerationService
    {
        $guard = Mockery::mock(PipelineGuard::class);
        return new TiktokGenerationService($guard);
    }

    public function test_parses_complete_envelope_with_5_hashtags(): void
    {
        $json = json_encode([
            'status' => 'complete',
            'title' => 'AI agents in 2026',
            'caption' => 'AI agents production playbook. Front-loaded keyword. Read more https://example.com/post',
            'hashtags' => ['#aibuilders', '#aiagents', '#claudecode', '#vibecoding', '#solopreneur'],
            'validation' => ['passed' => true, 'failures' => [], 'notes' => []],
        ]);

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertIsArray($parsed);
        $this->assertSame('complete', $parsed['status']);
        $this->assertCount(5, $parsed['hashtags']);
    }

    public function test_parses_failed_envelope(): void
    {
        $json = json_encode([
            'status' => 'failed',
            'error' => 'Schema validation failed: hashtags exceeded 8',
            'error_code' => 'guideline_conflict',
        ]);

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertSame('failed', $parsed['status']);
    }

    public function test_returns_null_on_garbage(): void
    {
        $this->assertNull($this->svc()->parseOrchestratorOutput(''));
        $this->assertNull($this->svc()->parseOrchestratorOutput('not json'));
    }
}
