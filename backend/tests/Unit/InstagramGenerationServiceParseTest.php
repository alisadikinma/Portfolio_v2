<?php

namespace Tests\Unit;

use App\Services\InstagramGenerationService;
use App\Services\PipelineGuard;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for InstagramGenerationService::parseOrchestratorOutput.
 *
 * Parser is lifted from BaseSocialGenerationService and shared with TikTok +
 * Facebook services. Tolerates Sonnet preamble narration, ```json fences,
 * and trailing markdown — same patterns hardened on LinkedIn pipeline May 2.
 */
class InstagramGenerationServiceParseTest extends TestCase
{
    private function svc(): InstagramGenerationService
    {
        $guard = Mockery::mock(PipelineGuard::class);
        return new InstagramGenerationService($guard);
    }

    public function test_parses_clean_complete_envelope(): void
    {
        $json = json_encode([
            'status' => 'complete',
            'title' => 'How AI agents fail in production',
            'caption' => 'Most AI agents fail in production. Here is why.',
            'hashtags' => ['#aibuilders', '#aiagents', '#claudecode', '#solopreneur'],
            'suggested_time_slot' => [
                'day_of_week' => 'tuesday',
                'hour' => 19,
                'timezone' => 'Asia/Jakarta',
            ],
            'validation' => ['passed' => true, 'failures' => [], 'notes' => []],
        ]);

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertIsArray($parsed);
        $this->assertSame('complete', $parsed['status']);
        $this->assertCount(4, $parsed['hashtags']);
    }

    public function test_parses_failed_envelope(): void
    {
        $json = json_encode([
            'status' => 'failed',
            'error' => 'RAG bundle missing',
            'error_code' => 'rag_missing',
        ]);

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertSame('failed', $parsed['status']);
        $this->assertSame('rag_missing', $parsed['error_code']);
    }

    public function test_parses_sonnet_preamble_with_fenced_json(): void
    {
        // Real-world Sonnet output: explanatory preamble then ```json fence.
        // Same failure mode as LinkedIn May 2 — preamble has no `{` so the
        // anchor scanner skips past it cleanly.
        $raw = "I have authored the Instagram caption. Here is the output:\n\n"
            . "```json\n"
            . json_encode([
                'status' => 'complete',
                'title' => 'Test hook',
                'caption' => 'Body here',
                'hashtags' => ['#a', '#b', '#c'],
                'validation' => ['passed' => true, 'failures' => [], 'notes' => []],
            ])
            . "\n```\n\nDone.";

        $parsed = $this->svc()->parseOrchestratorOutput($raw);

        $this->assertIsArray($parsed);
        $this->assertSame('complete', $parsed['status']);
        $this->assertSame(3, count($parsed['hashtags']));
    }

    public function test_parses_pure_fenced_json_no_preamble(): void
    {
        $raw = "```json\n"
            . json_encode([
                'status' => 'complete',
                'title' => 'Test',
                'caption' => 'Body',
                'hashtags' => ['#a', '#b', '#c'],
                'validation' => ['passed' => true],
            ])
            . "\n```";

        $parsed = $this->svc()->parseOrchestratorOutput($raw);

        $this->assertIsArray($parsed);
        $this->assertSame('Test', $parsed['title']);
    }

    public function test_tolerates_trailing_narration(): void
    {
        $json = json_encode([
            'status' => 'complete',
            'title' => 'x',
            'caption' => 'y',
            'hashtags' => ['#a', '#b', '#c'],
            'validation' => ['passed' => true],
        ]);
        $raw = $json . "\n\nLet me know if you'd like to adjust anything!";

        $parsed = $this->svc()->parseOrchestratorOutput($raw);

        $this->assertIsArray($parsed);
        $this->assertSame('complete', $parsed['status']);
    }

    public function test_returns_null_on_empty_string(): void
    {
        $this->assertNull($this->svc()->parseOrchestratorOutput(''));
        $this->assertNull($this->svc()->parseOrchestratorOutput('   '));
    }

    public function test_returns_null_on_no_json_object(): void
    {
        $this->assertNull($this->svc()->parseOrchestratorOutput('No JSON here, just prose.'));
    }

    public function test_returns_null_on_malformed_json(): void
    {
        $raw = '{ "status": "complete", "title": "broken'; // unterminated

        $this->assertNull($this->svc()->parseOrchestratorOutput($raw));
    }

    public function test_handles_braces_inside_strings_correctly(): void
    {
        // String-aware brace scanner must NOT misinterpret { or } inside JSON
        // string values as structural braces. Here the caption contains
        // a literal `{` and `}` which would break a naive scanner.
        $json = json_encode([
            'status' => 'complete',
            'title' => 'Templates explained',
            'caption' => 'Use {{var}} syntax for templates. End with } closer.',
            'hashtags' => ['#a', '#b', '#c'],
            'validation' => ['passed' => true],
        ]);

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertIsArray($parsed);
        $this->assertStringContainsString('{{var}}', $parsed['caption']);
    }
}
