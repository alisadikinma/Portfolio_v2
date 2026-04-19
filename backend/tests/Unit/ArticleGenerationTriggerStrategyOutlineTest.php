<?php

namespace Tests\Unit;

use App\Services\ArticleGenerationService;
use Tests\TestCase;

/**
 * Phase B.4: ArticleGenerationService::triggerStrategyOutline()
 *
 * Verifies that triggerStrategyOutline builds the /article-strategy-outline CLI
 * prompt with only the core identity flags (--idea-id, --api-url, --api-token),
 * since the skill reads research/topic/languages/keyword from the DB via
 * GET /content-ideas/{id}. Always runs on Sonnet with refs-strategy-outline.md.
 *
 * Uses a test-double subclass that overrides the protected executePrompt method
 * to capture arguments without actually running SSH/local processes.
 */
class ArticleGenerationTriggerStrategyOutlineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.article_generation.api_url' => 'https://api.test/api',
            'services.article_generation.api_token' => 'test-token-abc',
            'services.article_generation.model_strategy_outline' => 'sonnet',
            'services.article_generation.refs_strategy_outline' => '/home/claudesn/refs-strategy-outline.md',
        ]);
    }

    private function makeServiceDouble(): ArticleGenerationService
    {
        return new class extends ArticleGenerationService {
            public array $capturedCall = [];

            protected function executePrompt(string $prompt, int $ideaId, string $phase, string $model = '', string $refsFile = ''): array
            {
                $this->capturedCall = compact('prompt', 'ideaId', 'phase', 'model', 'refsFile');
                return ['success' => true, 'pid' => 5150, 'error' => null];
            }
        };
    }

    public function test_resolves_to_sonnet_model_from_config(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerStrategyOutline(42);

        $this->assertSame('sonnet', $service->capturedCall['model']);
    }

    public function test_uses_configured_refs_strategy_outline_file(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerStrategyOutline(42);

        $this->assertSame('/home/claudesn/refs-strategy-outline.md', $service->capturedCall['refsFile']);
    }

    public function test_phase_label_is_strategy_outline(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerStrategyOutline(42);

        $this->assertSame('strategy-outline', $service->capturedCall['phase']);
    }

    public function test_idea_id_is_forwarded_to_execute_prompt(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerStrategyOutline(42);

        $this->assertSame(42, $service->capturedCall['ideaId']);
    }

    public function test_prompt_starts_with_article_strategy_outline_and_identity_flags(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerStrategyOutline(42);

        $prompt = $service->capturedCall['prompt'];

        $this->assertStringStartsWith('/article-strategy-outline --idea-id 42 --api-url https://api.test/api --api-token test-token-abc', $prompt);
    }

    public function test_prompt_is_flagless_beyond_core_identity_flags(): void
    {
        $service = $this->makeServiceDouble();

        $service->triggerStrategyOutline(42);

        $prompt = $service->capturedCall['prompt'];

        $this->assertStringNotContainsString('--topic', $prompt);
        $this->assertStringNotContainsString('--languages', $prompt);
        $this->assertStringNotContainsString('--keyword', $prompt);
        $this->assertStringNotContainsString('--instructions', $prompt);
        $this->assertStringNotContainsString('--research-tier', $prompt);
    }

    public function test_returns_success_shape_from_execute_prompt(): void
    {
        $service = $this->makeServiceDouble();

        $result = $service->triggerStrategyOutline(42);

        $this->assertSame([
            'success' => true,
            'pid' => 5150,
            'error' => null,
        ], $result);
    }
}
