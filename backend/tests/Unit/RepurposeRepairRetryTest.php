<?php

namespace Tests\Unit;

use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Phase C — runRepurposeParsed: run the CLI, parse the JSON object, validate
 * required keys, and fire ONE repair retry when exec SUCCEEDED but the output is
 * unparseable / missing keys. Exec failures (timeout/ssh) are NOT repaired.
 *
 * Driver forced to 'local' so each attempt = exactly one Process invocation.
 *
 * @see docs/plans/2026-06-11-repurpose-llm-hardening.md
 */
class RepurposeRepairRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.repurpose.driver' => 'local']);
        config(['services.repurpose.empty_mcp_config' => '']);
    }

    private function harness(): object
    {
        return new class {
            use RunsRepurposeClaudeCli;

            /** @param string[] $keys */
            public function call(string $prompt, array $keys): array
            {
                return $this->runRepurposeParsed($prompt, 'vision', $keys, 'sonnet');
            }
        };
    }

    public function test_valid_first_attempt_no_repair(): void
    {
        Process::fake(['*' => Process::sequence()->push('{"claims":["x"]}')]);

        $res = $this->harness()->call('PROMPT', ['claims']);

        $this->assertTrue($res['success']);
        $this->assertFalse($res['repaired']);
        $this->assertSame(['x'], $res['parsed']['claims']);
        Process::assertRanTimes(fn () => true, 1);
    }

    public function test_unparseable_then_repaired(): void
    {
        Process::fake(['*' => Process::sequence()
            ->push('{"claims": "oops')          // unterminated → unparseable
            ->push('{"claims":["x"]}')]);        // repair succeeds

        $res = $this->harness()->call('PROMPT', ['claims']);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['repaired']);
        $this->assertSame(['x'], $res['parsed']['claims']);
        Process::assertRanTimes(fn () => true, 2);
    }

    public function test_missing_required_key_triggers_repair(): void
    {
        Process::fake(['*' => Process::sequence()
            ->push('{"title":"t"}')                       // missing body
            ->push('{"title":"t","body":"<p>x</p>"}')]);  // repair fills it

        $harness = new class {
            use RunsRepurposeClaudeCli;
            public function call(): array
            {
                return $this->runRepurposeParsed('PROMPT', 'rewrite', ['title', 'body'], 'sonnet');
            }
        };

        $res = $harness->call();

        $this->assertTrue($res['success']);
        $this->assertTrue($res['repaired']);
        $this->assertSame('<p>x</p>', $res['parsed']['body']);
    }

    public function test_repair_also_fails_returns_error(): void
    {
        Process::fake(['*' => Process::sequence()
            ->push('not json at all')
            ->push('still not json')]);

        $res = $this->harness()->call('PROMPT', ['claims']);

        $this->assertFalse($res['success']);
        $this->assertTrue($res['repaired']);
        $this->assertSame('unparseable_after_repair', $res['error']);
    }

    public function test_exec_failure_no_repair(): void
    {
        Process::fake(['*' => Process::result(output: '', exitCode: 124)]);

        $res = $this->harness()->call('PROMPT', ['claims']);

        $this->assertFalse($res['success']);
        $this->assertFalse($res['repaired']);
        Process::assertRanTimes(fn () => true, 1);
    }
}
