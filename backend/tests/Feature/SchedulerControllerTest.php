<?php

namespace Tests\Feature;

use App\Models\ScheduledCommand;
use App\Models\User;
use Database\Seeders\ScheduledCommandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase E feature coverage for the /admin/scheduler endpoints.
 *
 * Asserts:
 *  - GET returns category-grouped resources with cron-derived next_run_at
 *  - PUT validates cron expression + blocks placeholder edits
 *  - POST /run dispatches Artisan asynchronously, blocks placeholder + disabled rows
 */
class SchedulerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Local dev .env has APP_URL pointing at the XAMPP subpath
        // (`/Portfolio_v2/backend/public`). Force the root URL back to bare
        // localhost so test getJson('/api/...') paths resolve. Same workaround
        // RegenerateFromCompletedTest uses (per CLAUDE.md May 6 note about
        // documented subpath APP_URL mismatch).
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // Seed authoritative schedule inventory (18 rows, 14 real + 4 placeholders).
        Artisan::call('db:seed', ['--class' => ScheduledCommandSeeder::class]);
    }

    /** @test */
    public function test_index_returns_grouped_by_category_with_next_run(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $response = $this->getJson('/api/admin/scheduler');

        $response->assertStatus(200);

        $payload = $response->json();
        $this->assertArrayHasKey('groups', $payload);

        $groups = $payload['groups'];
        $this->assertArrayHasKey('linkedin', $groups);
        $this->assertIsArray($groups['linkedin']);
        $this->assertNotEmpty($groups['linkedin']);

        $first = $groups['linkedin'][0];
        $this->assertArrayHasKey('cron_expression', $first);
        $this->assertArrayHasKey('next_run_at', $first);
        $this->assertArrayHasKey('last_status', $first);
        $this->assertArrayHasKey('signature', $first);
        $this->assertArrayHasKey('is_placeholder', $first);

        // next_run_at should be a real ISO-8601 future timestamp (or null only for invalid cron)
        if ($first['next_run_at'] !== null) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $first['next_run_at']
            );
        }
    }

    /** @test */
    public function test_index_requires_auth(): void
    {
        $response = $this->getJson('/api/admin/scheduler');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_update_modifies_enabled(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $cmd = ScheduledCommand::where('signature', 'linkedin:scan-blog')->first();
        $this->assertNotNull($cmd, 'Seeder must include linkedin:scan-blog');

        $response = $this->putJson("/api/admin/scheduler/{$cmd->id}", [
            'enabled' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse((bool) $cmd->fresh()->enabled);
    }

    /** @test */
    public function test_update_modifies_cron_expression(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $cmd = ScheduledCommand::where('signature', 'linkedin:scan-blog')->first();

        $response = $this->putJson("/api/admin/scheduler/{$cmd->id}", [
            'cron_expression' => '0 6 * * *',
        ]);

        $response->assertStatus(200);
        $this->assertSame('0 6 * * *', $cmd->fresh()->cron_expression);
    }

    /** @test */
    public function test_update_rejects_invalid_cron_expression(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $cmd = ScheduledCommand::where('signature', 'linkedin:scan-blog')->first();

        $response = $this->putJson("/api/admin/scheduler/{$cmd->id}", [
            'cron_expression' => 'not a cron',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_update_rejects_placeholder_edits(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $placeholder = ScheduledCommand::where('is_placeholder', true)->first();
        $this->assertNotNull($placeholder, 'Seeder must include placeholder rows');

        $response = $this->putJson("/api/admin/scheduler/{$placeholder->id}", [
            'enabled' => false,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_run_dispatches_artisan_queue_for_real_command(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        Queue::fake();

        $cmd = ScheduledCommand::where('signature', 'linkedin:scan-blog')
            ->where('enabled', true)
            ->first();
        $this->assertNotNull($cmd);

        $response = $this->postJson("/api/admin/scheduler/{$cmd->id}/run");

        $response->assertStatus(202);

        // Artisan::queue dispatches a QueuedCommand job under the hood.
        Queue::assertPushed(\Illuminate\Foundation\Console\QueuedCommand::class);

        // Optimistic mutation should land
        $fresh = $cmd->fresh();
        $this->assertNotNull($fresh->last_run_at);
        $this->assertSame('running', $fresh->last_status);
    }

    /** @test */
    public function test_run_rejects_placeholder(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $placeholder = ScheduledCommand::where('is_placeholder', true)->first();
        $this->assertNotNull($placeholder);

        $response = $this->postJson("/api/admin/scheduler/{$placeholder->id}/run");

        $response->assertStatus(403);
    }

    /** @test */
    public function test_run_rejects_disabled(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $cmd = ScheduledCommand::where('signature', 'linkedin:scan-blog')->first();
        $cmd->update(['enabled' => false]);

        $response = $this->postJson("/api/admin/scheduler/{$cmd->id}/run");

        $response->assertStatus(403);
    }
}
