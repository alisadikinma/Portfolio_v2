<?php

namespace Tests\Unit;

use App\Console\Commands\ResearchPostingTimeRules;
use App\Models\PostingTimeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Coverage for `posting-rules:research` artisan command.
 *
 * Strategy: subclass the command and override `runResearchProcess()` to
 * inject canned Sonnet stdout. Tests focus on:
 *   - JSON parser tolerates Sonnet preamble + ```json fences
 *   - Envelope validation (sources[] + rules[] required)
 *   - --dry-run skips DB writes
 *   - --force bypasses staleness gate
 *   - DB upserts respect UNIQUE constraint
 */
class ResearchPostingTimeRulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeCommandWithStdout(string $cannedStdout): ResearchPostingTimeRules
    {
        return new class($cannedStdout) extends ResearchPostingTimeRules {
            public function __construct(private string $cannedStdout)
            {
                parent::__construct();
            }
            protected function runResearchProcess(string $prompt): string
            {
                return $this->cannedStdout;
            }
        };
    }

    public function test_parser_tolerates_sonnet_preamble_with_fenced_json(): void
    {
        $cmd = new ResearchPostingTimeRules();
        $stdout = <<<TXT
I researched 2026 LinkedIn data across Hootsuite, Buffer, and Sprout Social.

Here's the assembled JSON:

```json
{
  "sources": [
    {"url": "https://hootsuite.com/x", "title": "Hootsuite 2026"}
  ],
  "rules": [
    {"day_of_week": 2, "hour": 9, "score": 95, "note": "B2B peak"}
  ]
}
```

That covers the key slots.
TXT;

        $parsed = $cmd->parseResearchJson($stdout);
        $this->assertNotNull($parsed);
        $this->assertCount(1, $parsed['sources']);
        $this->assertCount(1, $parsed['rules']);
        $this->assertSame(95, $parsed['rules'][0]['score']);
    }

    public function test_parser_returns_null_on_garbage_input(): void
    {
        $cmd = new ResearchPostingTimeRules();
        $this->assertNull($cmd->parseResearchJson(''));
        $this->assertNull($cmd->parseResearchJson('absolutely no json here, just prose'));
        $this->assertNull($cmd->parseResearchJson('{"unclosed": "object'));
    }

    public function test_dry_run_does_not_write_db(): void
    {
        $cannedStdout = json_encode([
            'sources' => [['url' => 'https://x.com', 'title' => 'X']],
            'rules' => [
                ['day_of_week' => 2, 'hour' => 9, 'score' => 95, 'note' => 'peak'],
                ['day_of_week' => 4, 'hour' => 10, 'score' => 90],
            ],
        ]);

        $this->app->instance(ResearchPostingTimeRules::class, $this->makeCommandWithStdout($cannedStdout));

        $this->artisan('posting-rules:research', ['--platform' => 'linkedin', '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, PostingTimeRule::count(), 'Dry-run must not write to DB');
    }

    public function test_writes_rules_to_db_when_not_dry_run(): void
    {
        $cannedStdout = json_encode([
            'sources' => [['url' => 'https://x.com', 'title' => 'X']],
            'rules' => [
                ['day_of_week' => 2, 'hour' => 9, 'score' => 95, 'note' => 'peak'],
                ['day_of_week' => 4, 'hour' => 10, 'score' => 90, 'note' => 'good'],
            ],
        ]);

        $this->app->instance(ResearchPostingTimeRules::class, $this->makeCommandWithStdout($cannedStdout));

        $this->artisan('posting-rules:research', ['--platform' => 'linkedin', '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(2, PostingTimeRule::count());
        $tueRow = PostingTimeRule::query()->where('day_of_week', 2)->where('hour', 9)->first();
        $this->assertNotNull($tueRow);
        $this->assertSame(95, $tueRow->score);
        $this->assertSame('https://x.com', $tueRow->source_url);
    }

    public function test_staleness_gate_aborts_when_rules_fresh_and_not_forced(): void
    {
        // Seed a fresh rule
        PostingTimeRule::create([
            'platform' => 'linkedin',
            'day_of_week' => 1,
            'hour' => 8,
            'timezone' => 'Asia/Jakarta',
            'score' => 80,
            'audience' => 'b2b_tech',
            'last_researched_at' => Carbon::now()->subDays(10), // 10d old, < 90d default
        ]);

        // Even though we provide canned stdout, the command should abort BEFORE running the process
        $cannedStdout = json_encode([
            'sources' => [['url' => 'https://x.com', 'title' => 'X']],
            'rules' => [['day_of_week' => 2, 'hour' => 9, 'score' => 95]],
        ]);
        $this->app->instance(ResearchPostingTimeRules::class, $this->makeCommandWithStdout($cannedStdout));

        $this->artisan('posting-rules:research', ['--platform' => 'linkedin'])
            ->expectsOutputToContain('Rules fresh')
            ->assertExitCode(0);

        // The seeded rule is still the only one
        $this->assertSame(1, PostingTimeRule::count());
        $this->assertSame(80, PostingTimeRule::query()->first()->score);
    }

    public function test_force_flag_overrides_staleness_gate(): void
    {
        PostingTimeRule::create([
            'platform' => 'linkedin',
            'day_of_week' => 1,
            'hour' => 8,
            'timezone' => 'Asia/Jakarta',
            'score' => 50,
            'audience' => 'b2b_tech',
            'last_researched_at' => Carbon::now()->subDays(2),
        ]);

        $cannedStdout = json_encode([
            'sources' => [['url' => 'https://x.com', 'title' => 'X']],
            'rules' => [['day_of_week' => 1, 'hour' => 8, 'score' => 99, 'note' => 'updated']],
        ]);
        $this->app->instance(ResearchPostingTimeRules::class, $this->makeCommandWithStdout($cannedStdout));

        $this->artisan('posting-rules:research', ['--platform' => 'linkedin', '--force' => true])
            ->assertExitCode(0);

        // Score should be upserted from 50 -> 99
        $row = PostingTimeRule::query()->where('day_of_week', 1)->where('hour', 8)->first();
        $this->assertSame(99, $row->score);
    }

    public function test_rejects_envelope_with_no_sources(): void
    {
        $cannedStdout = json_encode([
            'rules' => [['day_of_week' => 2, 'hour' => 9, 'score' => 95]],
        ]);
        $this->app->instance(ResearchPostingTimeRules::class, $this->makeCommandWithStdout($cannedStdout));

        $this->artisan('posting-rules:research', ['--platform' => 'linkedin', '--force' => true])
            ->assertExitCode(1);

        $this->assertSame(0, PostingTimeRule::count());
    }

    public function test_rejects_envelope_with_no_rules(): void
    {
        $cannedStdout = json_encode([
            'sources' => [['url' => 'https://x.com', 'title' => 'X']],
            'rules' => [],
        ]);
        $this->app->instance(ResearchPostingTimeRules::class, $this->makeCommandWithStdout($cannedStdout));

        $this->artisan('posting-rules:research', ['--platform' => 'linkedin', '--force' => true])
            ->assertExitCode(1);
    }

    public function test_rejects_invalid_platform(): void
    {
        $this->artisan('posting-rules:research', ['--platform' => 'myspace'])
            ->assertExitCode(1);
    }

    public function test_skips_malformed_rule_rows_but_persists_valid_ones(): void
    {
        $cannedStdout = json_encode([
            'sources' => [['url' => 'https://x.com', 'title' => 'X']],
            'rules' => [
                ['day_of_week' => 2, 'hour' => 9, 'score' => 95],   // valid
                ['day_of_week' => 7, 'hour' => 9, 'score' => 80],   // invalid dow
                ['day_of_week' => 3, 'hour' => 25, 'score' => 80],  // invalid hour
                ['day_of_week' => 4, 'hour' => 10, 'score' => 150], // invalid score
                ['day_of_week' => 5, 'hour' => 11, 'score' => 88],  // valid
            ],
        ]);
        $this->app->instance(ResearchPostingTimeRules::class, $this->makeCommandWithStdout($cannedStdout));

        $this->artisan('posting-rules:research', ['--platform' => 'linkedin', '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(2, PostingTimeRule::count());
    }
}
