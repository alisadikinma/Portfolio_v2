<?php

namespace Tests\Unit;

use App\Jobs\PublishViaPubler;
use ReflectionMethod;
use Tests\TestCase;

/**
 * isTransientError classification — decides whether a Publer error rethrows for
 * a queue retry (transient) or marks the sibling failed (permanent).
 *
 * The June 2026 fix reclassifies Publer's media-from-url concurrency 403
 * ("another download-media job is still running") + rate-limit (429) as
 * transient — they self-heal on retry, unlike a genuine plan/scope 403.
 */
class PublishViaPublerTransientTest extends TestCase
{
    private function classify(string $message): bool
    {
        $job = new PublishViaPubler('instagram', 1);
        $m = new ReflectionMethod($job, 'isTransientError');
        $m->setAccessible(true);

        return (bool) $m->invoke($job, $message);
    }

    public function test_concurrency_403_is_transient(): void
    {
        $this->assertTrue($this->classify(
            'Publer media busy (403): another download-media job is still running — retry shortly.'
        ));
    }

    public function test_rate_limit_is_transient(): void
    {
        $this->assertTrue($this->classify('Publer rate limit hit (429). Back off and retry.'));
    }

    public function test_5xx_and_network_are_transient(): void
    {
        $this->assertTrue($this->classify('Publer createPost failed: HTTP 503'));
        $this->assertTrue($this->classify('cURL error: Connection timeout'));
    }

    public function test_plan_scope_403_is_permanent(): void
    {
        $this->assertFalse($this->classify(
            'Publer access denied (403). Plan/scope may be insufficient — verify Publer subscription tier.'
        ));
    }

    /**
     * Routes to the dedicated cross-post worker pool, not the shared `default`
     * queue (where it would starve behind multi-minute /carousel-gen SSH jobs —
     * production incident draft 157, 2026-06-12).
     */
    public function test_runs_on_social_crosspost_queue(): void
    {
        $this->assertSame('social-crosspost', (new PublishViaPubler('instagram', 1))->queue);
    }
}
