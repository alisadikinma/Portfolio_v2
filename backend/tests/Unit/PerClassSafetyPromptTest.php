<?php

namespace Tests\Unit;

use App\Enums\PipelineErrorClass;
use App\Services\ArticleGenerationService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase E verification: ensure ArticleGenerationService::buildSafetyRewritePrompt
 * branches its system instructions correctly per PipelineErrorClass — so when
 * GeminiGen rejects a prompt with "PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD" the
 * Sonnet rewrite is told to strip ONLY persons (keep brands), and analogously
 * for the other classes. Reflection-based because the prompt builder is
 * private — keeping it private preserves the public API of the rewrite method.
 */
class PerClassSafetyPromptTest extends TestCase
{
    private function invokeBuildPrompt(
        ?PipelineErrorClass $errorClass,
        string $vd = 'a tech CEO speaking on stage at a conference',
        string $reason = 'PUBLIC_ERROR_TEST'
    ): string {
        $service = app(ArticleGenerationService::class);
        $reflection = new ReflectionMethod($service, 'buildSafetyRewritePrompt');
        $reflection->setAccessible(true);
        return $reflection->invoke($service, $vd, $reason, [], $errorClass);
    }

    public function test_policy_person_branch_strips_persons_keeps_brands(): void
    {
        $prompt = $this->invokeBuildPrompt(PipelineErrorClass::PolicyPerson);
        $this->assertStringContainsString('Strip ONLY proper nouns referring to people', $prompt);
        $this->assertStringContainsString('KEEP brand mentions', $prompt);
    }

    public function test_policy_brand_branch_strips_brands_keeps_persons(): void
    {
        $prompt = $this->invokeBuildPrompt(PipelineErrorClass::PolicyBrand);
        $this->assertStringContainsString('Strip ONLY brand names', $prompt);
        $this->assertStringContainsString('KEEP persons', $prompt);
    }

    public function test_policy_minor_branch_forces_adult_descriptor(): void
    {
        $prompt = $this->invokeBuildPrompt(PipelineErrorClass::PolicyMinor);
        $this->assertStringContainsString('adult professional, 30+', $prompt);
        $this->assertStringContainsString('Strip school, classroom, youth', $prompt);
    }

    public function test_policy_nsfw_branch_softens_tension(): void
    {
        $prompt = $this->invokeBuildPrompt(PipelineErrorClass::PolicyNsfw);
        $this->assertStringContainsString('Soften any tension, conflict, or violent words', $prompt);
        $this->assertStringContainsString('KEEP scene framing', $prompt);
    }

    public function test_policy_generic_branch_strips_all_proper_nouns(): void
    {
        $prompt = $this->invokeBuildPrompt(PipelineErrorClass::PolicyGeneric);
        $this->assertStringContainsString('Remove ALL proper nouns', $prompt);
    }

    public function test_null_error_class_falls_back_to_generic_strip(): void
    {
        $prompt = $this->invokeBuildPrompt(null);
        // Backward-compat: existing callers passing 3 args still get the
        // historical aggressive-strip behavior.
        $this->assertStringContainsString('Remove ALL proper nouns', $prompt);
    }

    public function test_prompt_includes_original_visual_direction(): void
    {
        $vd = 'a unique scene with very specific lighting cues';
        $prompt = $this->invokeBuildPrompt(PipelineErrorClass::PolicyPerson, $vd);
        $this->assertStringContainsString($vd, $prompt);
    }

    public function test_prompt_includes_rejection_reason_text(): void
    {
        $reason = 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD: detected named individual';
        $prompt = $this->invokeBuildPrompt(PipelineErrorClass::PolicyPerson, 'a person', $reason);
        $this->assertStringContainsString($reason, $prompt);
    }
}
