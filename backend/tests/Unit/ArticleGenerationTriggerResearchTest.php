<?php

namespace Tests\Unit;

use App\Services\ArticleGenerationService;
use Tests\TestCase;

/**
 * Phase B.4: ArticleGenerationService::triggerResearch()
 *
 * Verifies that triggerResearch builds the correct /article-research CLI prompt,
 * resolves model per tier via resolveResearchModel (Opus for deep, Sonnet for
 * quick), pulls the refs file from config('services.article_generation.refs_research'),
 * labels the phase 'research', and returns the expected array shape.
 *
 * Uses a test-double subclass that overrides the protected executePrompt method
 * to capture arguments without actually running SSH/local processes.
 */
class ArticleGenerationTriggerResearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stable config for assertion predictability
        config([
            'services.article_generation.api_url' => 'https://api.test/api',
            'services.article_generation.api_token' => 'test-token-abc',
            'services.article_generation.model_research_deep' => 'opus',
            'services.article_generation.model_research_quick' => 'sonnet',
            'services.article_generation.refs_research' => '/home/claudesn/refs-research.md',
        ]);
    }

    private function makeServiceDouble(): ArticleGenerationService
    {
        return new class extends ArticleGenerationService {
            public array $capturedCall = [];

            protected function executePrompt(string $prompt, int $ideaId, string $phase, string $model = '', string $refsFile = ''): array
            {
                $this->capturedCall = compact('prompt', 'ideaId', 'phase', 'model', 'refsFile');
                return ['success' => true, 'pid' => 4242, 'error' => null];
            }
        };
    }

    public function test_deep_tier_resolves_to_opus_model(): void
    {
        $service = $this->makeServiceDouble();

        $result = $service->triggerResearch(42, [
            'topic' => 'AI tools',
            'languages' => ['en'],
            'keyword' => 'ai',
        ], 'deep');

        $this->assertSame('opus', $service->capturedCall['model']);
        $this->assertSame('/home/claudesn/refs-research.md', $service->capturedCall['refsFile']);
        $this->assertSame('research', $service->capturedCall['phase']);
        $this->assertSame(42, $service->capturedCall['ideaId']);

        $this->assertSame([
            'success' => true,
            'pid' => 4242,
            'error' => null,
        ], $result);
    }

    public function test_quick_tier_resolves_to_sonnet_model(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerResearch(7, [
            'topic' => 'Simple topic',
            'languages' => ['id'],
        ], 'quick');

        $this->assertSame('sonnet', $service->capturedCall['model']);
    }

    public function test_prompt_starts_with_article_research_and_core_flags(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerResearch(42, [
            'topic' => 'AI tools',
            'languages' => ['en'],
            'keyword' => 'ai',
        ], 'deep');

        $prompt = $service->capturedCall['prompt'];

        $this->assertStringStartsWith('/article-research --idea-id 42 --api-url https://api.test/api --api-token test-token-abc', $prompt);
    }

    public function test_prompt_contains_research_tier_flag(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerResearch(42, [
            'topic' => 'AI tools',
            'languages' => ['en'],
        ], 'deep');

        $this->assertStringContainsString('--research-tier deep', $service->capturedCall['prompt']);
    }

    public function test_prompt_contains_topic_languages_keyword_flags(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerResearch(42, [
            'topic' => 'AI tools',
            'languages' => ['en', 'id'],
            'keyword' => 'ai',
        ], 'deep');

        $prompt = $service->capturedCall['prompt'];

        $this->assertStringContainsString('--topic "AI tools"', $prompt);
        $this->assertStringContainsString('--languages en,id', $prompt);
        $this->assertStringContainsString('--keyword "ai"', $prompt);
    }

    public function test_prompt_omits_keyword_flag_when_not_provided(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerResearch(42, [
            'topic' => 'AI tools',
            'languages' => ['en'],
        ], 'quick');

        $this->assertStringNotContainsString('--keyword', $service->capturedCall['prompt']);
    }

    public function test_prompt_includes_instructions_when_provided(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerResearch(42, [
            'topic' => 'AI tools',
            'languages' => ['en'],
            'instructions' => 'Focus on enterprise buyers',
        ], 'deep');

        $this->assertStringContainsString('--instructions "Focus on enterprise buyers"', $service->capturedCall['prompt']);
    }

    public function test_returns_success_shape_from_execute_prompt(): void
    {
        $service = $this->makeServiceDouble();

        $result = $service->triggerResearch(42, [
            'topic' => 'AI tools',
            'languages' => ['en'],
        ], 'quick');

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('pid', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertTrue($result['success']);
        $this->assertSame(4242, $result['pid']);
        $this->assertNull($result['error']);
    }

    public function test_rejects_invalid_tier(): void
    {
        $service = new class extends \App\Services\ArticleGenerationService {
            protected function executePrompt(string $prompt, int $ideaId, string $phase, string $model = '', string $refsFile = ''): array
            {
                return ['success' => true, 'pid' => 1, 'error' => null];
            }
        };
        $this->expectException(\InvalidArgumentException::class);
        $service->triggerResearch(42, ['topic' => 't', 'languages' => ['en']], 'turbo');
    }
}
