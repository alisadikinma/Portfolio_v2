<?php

namespace Tests\Unit;

use App\Services\RepurposeResearchService;
use App\Services\RepurposeRewriteService;
use App\Services\SlideVisionExtractor;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase B — every repurpose prompt must carry the strict-JSON directive that
 * tells Sonnet to escape inner quotes, emit compact JSON, and never truncate.
 * Root cause of the production failures was unescaped " and truncated output.
 *
 * @see docs/plans/2026-06-11-repurpose-llm-hardening.md
 */
class RepurposePromptDirectiveTest extends TestCase
{
    private function build(object $service, array $args): string
    {
        $m = new ReflectionMethod($service, 'buildPrompt');
        $m->setAccessible(true);
        return (string) $m->invokeArgs($service, $args);
    }

    public function test_vision_prompt_has_strict_json_directive(): void
    {
        $prompt = $this->build(new SlideVisionExtractor(), [['/tmp/slide-01.jpg'], 'caption']);
        $this->assertStringContainsString('Escape EVERY double-quote', $prompt);
        $this->assertStringContainsString('Do not truncate', $prompt);
    }

    public function test_research_prompt_has_strict_json_directive(): void
    {
        $prompt = $this->build(new RepurposeResearchService(), [['claim one'], 'narrative', 'https://instagram.com/p/x']);
        $this->assertStringContainsString('Escape EVERY double-quote', $prompt);
        $this->assertStringContainsString('Do not truncate', $prompt);
    }

    public function test_rewrite_prompt_has_strict_json_directive(): void
    {
        $extracted = ['narrative' => 'n', 'slides' => [['text' => 's']]];
        $research = ['verdicts' => [['claim' => 'x', 'status' => 'ok']]];
        $prompt = $this->build(new RepurposeRewriteService(), [$extracted, $research, '']);
        $this->assertStringContainsString('Escape EVERY double-quote', $prompt);
        $this->assertStringContainsString('Do not truncate', $prompt);
    }
}
