<?php

namespace Tests\Unit;

use App\Services\LinkedInGenerationService;
use App\Services\PipelineGuard;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LinkedInGenerationService::parseOrchestratorOutput.
 *
 * The plugin emits a single JSON blob to stdout matching OrchestratorOutputSchema.
 * It may emit interactive narration after the JSON in dev mode, so the parser
 * must balance braces rather than relying on the whole stdout being valid JSON.
 */
class LinkedInGenerationServiceParseTest extends TestCase
{
    private function svc(): LinkedInGenerationService
    {
        $guard = Mockery::mock(PipelineGuard::class);
        return new LinkedInGenerationService($guard);
    }

    public function test_parses_clean_json_blob(): void
    {
        $json = json_encode([
            'status' => 'complete',
            'format' => 'text',
            'brief' => ['hook' => 'x'],
            'post' => ['post_text' => 'hello world', 'hashtags' => ['#ai']],
            'carousel' => null,
            'validation' => ['depth_score' => 85, 'passed' => true],
            'generated_at' => '2026-04-23T09:00:00Z',
        ]);

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertIsArray($parsed);
        $this->assertSame('complete', $parsed['status']);
        $this->assertSame(85, $parsed['validation']['depth_score']);
    }

    public function test_strips_markdown_fence(): void
    {
        $json = "```json\n" . json_encode(['status' => 'complete', 'format' => 'text']) . "\n```";

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertIsArray($parsed);
        $this->assertSame('complete', $parsed['status']);
    }

    public function test_ignores_narration_after_json(): void
    {
        $json = json_encode(['status' => 'complete', 'format' => 'text', 'depth' => 90]);
        $withNarration = $json . "\n\nHook line: Great post! Depth: 90/100.";

        $parsed = $this->svc()->parseOrchestratorOutput($withNarration);

        $this->assertIsArray($parsed);
        $this->assertSame(90, $parsed['depth']);
    }

    public function test_handles_nested_braces_correctly(): void
    {
        $json = json_encode([
            'status' => 'complete',
            'validation' => ['nested' => ['deep' => ['value' => 1]]],
        ]);

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertIsArray($parsed);
        $this->assertSame(1, $parsed['validation']['nested']['deep']['value']);
    }

    public function test_handles_braces_inside_strings(): void
    {
        // The post_text contains a `}` which must not terminate the JSON early
        $payload = [
            'status' => 'complete',
            'post' => ['post_text' => 'curly brace { and } inside text'],
        ];
        $json = json_encode($payload);

        $parsed = $this->svc()->parseOrchestratorOutput($json);

        $this->assertIsArray($parsed);
        $this->assertStringContainsString('} inside text', $parsed['post']['post_text']);
    }

    public function test_returns_null_on_empty_string(): void
    {
        $this->assertNull($this->svc()->parseOrchestratorOutput(''));
    }

    public function test_returns_null_on_garbage_input(): void
    {
        $this->assertNull($this->svc()->parseOrchestratorOutput('not JSON at all'));
    }

    public function test_returns_null_on_unbalanced_braces(): void
    {
        $this->assertNull($this->svc()->parseOrchestratorOutput('{"status": "complete"'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
