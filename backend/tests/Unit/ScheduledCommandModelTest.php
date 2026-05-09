<?php

namespace Tests\Unit;

use App\Models\ScheduledCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — verifies the scheduled_commands table + model wiring
 * (cast for `arguments` JSON, scopes for enabled/non-placeholder filtering).
 *
 * Migration + model + factory must exist for these tests to pass.
 */
class ScheduledCommandModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_arguments_to_array(): void
    {
        $cmd = ScheduledCommand::factory()->create([
            'arguments' => ['--foo', '--bar'],
        ]);

        $fresh = ScheduledCommand::find($cmd->id);

        $this->assertIsArray($fresh->arguments);
        $this->assertCount(2, $fresh->arguments);
        $this->assertSame(['--foo', '--bar'], $fresh->arguments);
    }

    public function test_scope_enabled_filters_disabled_rows(): void
    {
        ScheduledCommand::factory()->count(2)->create(['enabled' => true]);
        ScheduledCommand::factory()->create(['enabled' => false]);

        $this->assertSame(2, ScheduledCommand::enabled()->count());
    }

    public function test_scope_not_placeholder_filters_placeholders(): void
    {
        ScheduledCommand::factory()->count(2)->create(['is_placeholder' => false]);
        ScheduledCommand::factory()->create(['is_placeholder' => true]);

        $this->assertSame(2, ScheduledCommand::notPlaceholder()->count());
    }
}
