<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class RetrySegmentEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('content.default_image_model', 'nano-banana-pro');
        Config::set('content.cover_branding.enabled', false);

        Http::fake([
            'api.geminigen.ai/*' => Http::response(['uuid' => 'fresh-uuid', 'status' => 1], 200),
        ]);

        $jobMock = Mockery::mock('alias:' . ImageGenerationJob::class);
        $jobMock->shouldReceive('create')->andReturn(new ImageGenerationJob());
        $collisionQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $collisionQuery->shouldReceive('exists')->andReturn(false);
        $jobMock->shouldReceive('where')->with('planned_filename', Mockery::any())->andReturn($collisionQuery);

        $aboutQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $aboutQuery->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $aboutQuery->shouldReceive('value')->with('value')->andReturn(null);

        $brandQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $brandQuery->shouldReceive('where')->andReturnUsing(function ($col, $key) {
            $stub = Mockery::mock('Illuminate\Database\Eloquent\Builder');
            $stub->shouldReceive('value')->with('value')->andReturn(
                $key === 'creator_brand_slug' ? 'alisadikinma' : null
            );
            return $stub;
        });

        $settingMock = Mockery::mock('alias:' . Setting::class);
        $settingMock->shouldReceive('where')->with('group', 'about')->andReturn($aboutQuery);
        $settingMock->shouldReceive('where')->with('group', 'creator_brand')->andReturn($brandQuery);

        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeIdea(int $retryCount, int $segments = 1): ContentIdea
    {
        $prompts = [];
        for ($i = 0; $i < $segments; $i++) {
            $prompts[] = [
                'type' => $i === 0 ? 'cover' : 'inline',
                'prompt_text' => "Segment {$i}",
                'status' => 'failed',
                'retry_count' => $retryCount,
                'failure_history' => [],
                'terminal_at' => null,
                'variations' => [['url' => null, 'job_uuid' => "old-{$i}", 'status' => 'failed', 'error' => 'boom']],
                'face_refs' => [],
                'style_refs' => [],
                'brand_refs' => [],
            ];
        }

        return ContentIdea::create([
            'title' => 'Endpoint idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => false,
            'generated_article' => ['image_prompts' => $prompts],
        ]);
    }

    /** @test */
    public function endpoint_dispatches_retry_and_returns_new_uuid(): void
    {
        $idea = $this->makeIdea(retryCount: 0);

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/retry-segment/0");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.uuid', 'fresh-uuid');
        $response->assertJsonPath('data.segment_index', 0);
        // retry_count stays at the pre-dispatch value (0 here) — it is
        // bumped only by handleSegmentFailure on actual failure callbacks.
        $response->assertJsonPath('data.retry_count', 0);
    }

    /** @test */
    public function endpoint_returns_404_for_missing_idea(): void
    {
        $this->postJson('/api/admin/content-engine/ideas/999999/retry-segment/0')
            ->assertStatus(404);
    }

    /** @test */
    public function endpoint_returns_404_for_out_of_range_segment(): void
    {
        $idea = $this->makeIdea(retryCount: 0, segments: 1);
        $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/retry-segment/5")
            ->assertStatus(404);
    }

    /** @test */
    public function endpoint_returns_409_when_retry_cap_reached(): void
    {
        $idea = $this->makeIdea(retryCount: ImageGenerationService::MAX_SEGMENT_ATTEMPTS);

        $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/retry-segment/0")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function endpoint_requires_auth(): void
    {
        // Clear the acting-as guard from setUp so the request is anonymous.
        $this->app['auth']->forgetGuards();
        $idea = $this->makeIdea(retryCount: 0);

        $response = $this->withHeader('Accept', 'application/json')
            ->postJson("/api/admin/content-engine/ideas/{$idea->id}/retry-segment/0");

        // auth:sanctum middleware rejects anonymous requests with 401.
        // Accepting anything else (especially 200) would silently pass if
        // middleware were removed from the route group — defeating the
        // entire purpose of this test.
        $response->assertStatus(401);
    }
}
