<?php

namespace Tests\Unit;

use App\Enums\PipelineErrorClass;
use App\Services\PipelineErrorClassifier;
use Tests\TestCase;

class PipelineErrorClassifierTest extends TestCase
{
    private PipelineErrorClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new PipelineErrorClassifier();
    }

    public function test_public_error_prominent_people_upload_returns_policy_person(): void
    {
        $this->assertSame(
            PipelineErrorClass::PolicyPerson,
            $this->classifier->classify('PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD: detected named individual')
        );
    }

    public function test_empty_string_returns_unknown(): void
    {
        $this->assertSame(PipelineErrorClass::Unknown, $this->classifier->classify(''));
    }

    public function test_null_returns_unknown(): void
    {
        $this->assertSame(PipelineErrorClass::Unknown, $this->classifier->classify(null));
    }

    public function test_whitespace_only_returns_unknown(): void
    {
        $this->assertSame(PipelineErrorClass::Unknown, $this->classifier->classify('   '));
    }

    public function test_ssh_connection_timed_out_returns_transient(): void
    {
        $this->assertSame(
            PipelineErrorClass::Transient,
            $this->classifier->classify('ssh: connect to host: Connection timed out')
        );
    }

    public function test_orchestrator_json_parse_failure_returns_deterministic_llm(): void
    {
        $this->assertSame(
            PipelineErrorClass::DeterministicLlm,
            $this->classifier->classify('Could not parse orchestrator JSON from stdout')
        );
    }

    public function test_public_error_minor_returns_policy_minor(): void
    {
        $this->assertSame(
            PipelineErrorClass::PolicyMinor,
            $this->classifier->classify('PUBLIC_ERROR_MINOR')
        );
    }

    public function test_public_error_unsafe_returns_policy_nsfw(): void
    {
        $this->assertSame(
            PipelineErrorClass::PolicyNsfw,
            $this->classifier->classify('PUBLIC_ERROR_UNSAFE: nudity detected')
        );
    }

    public function test_validation_rejected_with_depth_score_returns_permanent(): void
    {
        $this->assertSame(
            PipelineErrorClass::Permanent,
            $this->classifier->classify('validation rejected: depth score 65 below threshold 80')
        );
    }

    public function test_logo_detection_returns_policy_brand(): void
    {
        $this->assertSame(
            PipelineErrorClass::PolicyBrand,
            $this->classifier->classify('logo of OpenAI detected')
        );
    }

    public function test_safety_filter_returns_policy_generic(): void
    {
        $this->assertSame(
            PipelineErrorClass::PolicyGeneric,
            $this->classifier->classify('safety filter triggered')
        );
    }

    public function test_lowercase_public_error_code_still_matches(): void
    {
        $this->assertSame(
            PipelineErrorClass::PolicyPerson,
            $this->classifier->classify('public_error_prominent_people_upload: lowercase variant')
        );
    }

    public function test_max_attempts_exceeded_returns_transient(): void
    {
        $this->assertSame(
            PipelineErrorClass::Transient,
            $this->classifier->classify('MaxAttemptsExceededException: queue worker died')
        );
    }

    public function test_random_unmatched_string_returns_unknown(): void
    {
        $this->assertSame(
            PipelineErrorClass::Unknown,
            $this->classifier->classify('xkcd-random-gibberish-7f3b9')
        );
    }

    public function test_minors_freetext_returns_policy_minor(): void
    {
        $this->assertSame(
            PipelineErrorClass::PolicyMinor,
            $this->classifier->classify('content involving minors detected')
        );
    }

    public function test_zod_schema_error_returns_deterministic_llm(): void
    {
        $this->assertSame(
            PipelineErrorClass::DeterministicLlm,
            $this->classifier->classify('Zod validation: expected enum, received "carousel_v2"')
        );
    }

    public function test_reaper_stuck_in_generating_returns_transient(): void
    {
        // The linkedin:reap-stuck cron marks drafts exceeding the 20-min SSH
        // budget as failed with "Reaper: stuck in <state> for Nm" — almost
        // always the queue worker died mid-job. Fresh dispatch on a healthy
        // worker has a strong chance of succeeding.
        $this->assertSame(
            PipelineErrorClass::Transient,
            $this->classifier->classify('Reaper: stuck in generating for 21m (threshold=20m)')
        );
    }

    public function test_stuck_in_validating_returns_transient(): void
    {
        $this->assertSame(
            PipelineErrorClass::Transient,
            $this->classifier->classify('stuck in validating for 25m (threshold=20m)')
        );
    }

    public function test_process_timed_out_returns_transient(): void
    {
        $this->assertSame(
            PipelineErrorClass::Transient,
            $this->classifier->classify('Symfony Process timed out after 600 seconds')
        );
    }
}
