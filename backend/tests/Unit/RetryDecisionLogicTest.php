<?php

namespace Tests\Unit;

use App\Console\Commands\RetryFailedLinkedInPosts;
use App\Enums\PipelineErrorClass;
use App\Models\LinkedInPost;
use App\Services\PipelineErrorClassifier;
use App\Services\PipelineGuard;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase C verification: tests the pure decision logic of the retry cron
 * (decideAction) against various error class + retry count + age combinations.
 * DB-free — instantiates LinkedInPost without persisting and inspects the
 * private decideAction return value via reflection.
 */
class RetryDecisionLogicTest extends TestCase
{
    private function decide(LinkedInPost $draft, PipelineErrorClass $class): ?array
    {
        $command = new RetryFailedLinkedInPosts(
            new PipelineErrorClassifier(),
            new PipelineGuard()
        );
        $reflection = new ReflectionMethod($command, 'decideAction');
        $reflection->setAccessible(true);
        return $reflection->invoke($command, $draft, $class);
    }

    private function makeDraft(int $autoRetryCount, int $minutesSinceFail): LinkedInPost
    {
        $draft = new LinkedInPost();
        $draft->id = 999;
        $draft->auto_retry_count = $autoRetryCount;
        $draft->updated_at = now()->subMinutes($minutesSinceFail);
        return $draft;
    }

    public function test_transient_first_retry_after_5_min_eligible(): void
    {
        $draft = $this->makeDraft(autoRetryCount: 0, minutesSinceFail: 6);
        $action = $this->decide($draft, PipelineErrorClass::Transient);
        $this->assertNotNull($action);
        $this->assertSame(PipelineErrorClass::Transient, $action['class']);
    }

    public function test_transient_first_retry_within_5_min_skipped(): void
    {
        $draft = $this->makeDraft(autoRetryCount: 0, minutesSinceFail: 3);
        $this->assertNull($this->decide($draft, PipelineErrorClass::Transient));
    }

    public function test_transient_second_retry_requires_30_min_window(): void
    {
        $eligible = $this->makeDraft(autoRetryCount: 1, minutesSinceFail: 31);
        $tooSoon = $this->makeDraft(autoRetryCount: 1, minutesSinceFail: 15);
        $this->assertNotNull($this->decide($eligible, PipelineErrorClass::Transient));
        $this->assertNull($this->decide($tooSoon, PipelineErrorClass::Transient));
    }

    public function test_deterministic_llm_first_retry_after_30_min_eligible(): void
    {
        $draft = $this->makeDraft(autoRetryCount: 0, minutesSinceFail: 31);
        $action = $this->decide($draft, PipelineErrorClass::DeterministicLlm);
        $this->assertNotNull($action);
        $this->assertSame(PipelineErrorClass::DeterministicLlm, $action['class']);
    }

    public function test_deterministic_llm_second_retry_skipped(): void
    {
        // Same prompt twice = same parse failure. One retry covers transient
        // LLM service downtime; subsequent retries are wasteful.
        $draft = $this->makeDraft(autoRetryCount: 1, minutesSinceFail: 60);
        $this->assertNull($this->decide($draft, PipelineErrorClass::DeterministicLlm));
    }

    public function test_policy_classes_never_retry(): void
    {
        $draft = $this->makeDraft(autoRetryCount: 0, minutesSinceFail: 1000);
        foreach ([
            PipelineErrorClass::PolicyPerson,
            PipelineErrorClass::PolicyMinor,
            PipelineErrorClass::PolicyNsfw,
            PipelineErrorClass::PolicyBrand,
            PipelineErrorClass::PolicyGeneric,
        ] as $class) {
            $this->assertNull(
                $this->decide($draft, $class),
                "{$class->value} must not auto-retry — operator decision required"
            );
        }
    }

    public function test_permanent_class_never_retries(): void
    {
        $draft = $this->makeDraft(autoRetryCount: 0, minutesSinceFail: 1000);
        $this->assertNull($this->decide($draft, PipelineErrorClass::Permanent));
    }

    public function test_unknown_class_never_retries(): void
    {
        $draft = $this->makeDraft(autoRetryCount: 0, minutesSinceFail: 1000);
        $this->assertNull($this->decide($draft, PipelineErrorClass::Unknown));
    }
}
