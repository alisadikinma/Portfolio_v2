<?php

namespace Tests\Unit;

use App\Jobs\ExtractSlideContent;
use App\Jobs\ResearchRepurposeClaims;
use App\Jobs\RewriteRepurposeContent;
use Tests\TestCase;

/**
 * Phase A — timeout alignment. The CLI budget must match carousel-gen (900s) and
 * each step job's wall-clock $timeout must cover one attempt + one repair retry
 * (2x 900s + buffer) so the queue worker never kills a job mid-call.
 *
 * @see docs/plans/2026-06-11-repurpose-llm-hardening.md
 */
class RepurposeTimeoutConfigTest extends TestCase
{
    public function test_cli_timeout_default_is_900(): void
    {
        $this->assertSame(900, (int) config('services.repurpose.timeout'));
    }

    public function test_step_job_timeouts_cover_repair_headroom(): void
    {
        $this->assertSame(1920, (new ExtractSlideContent(1))->timeout);
        $this->assertSame(1920, (new ResearchRepurposeClaims(1))->timeout);
        $this->assertSame(1920, (new RewriteRepurposeContent(1))->timeout);
    }
}
