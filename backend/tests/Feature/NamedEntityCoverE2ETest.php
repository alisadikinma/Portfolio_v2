<?php

namespace Tests\Feature;

use App\Jobs\DispatchTelegramNotification;
use App\Models\ContentIdea;
use App\Models\EntityReference;
use App\Models\ImageGenerationJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * End-to-end integration guard for the named-entity-aware cover flow.
 *
 * Covers the cross-phase wiring from
 * docs/plans/2026-04-20-named-entity-aware-cover-generation.md:
 *
 *  Phase F manifest_needed progress → persist pending_manifest + dispatch
 *    Telegram job + flip to awaiting_manual_upload status
 *  Phase F upload-entity-reference → EntityReference row created
 *    + image_prompts[].entity_refs patched + status unblocked
 *  Phase D/C ImageGenerationService::triggerForIdea → enhancer gates
 *    creator face out (person entity present) + merges entity URLs into
 *    GeminiGen multipart file_urls + watermark + title overlay intact
 *
 * Lookup endpoint + EntityReferenceService internals are covered by
 * EntityRefsLookupEndpointTest and EntityReferenceServiceTest so this
 * test pre-seeds the entity_references row for White House (simulating
 * a previous plugin-initiated fetch) instead of re-exercising Wikidata.
 */
class NamedEntityCoverE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('content.default_image_model', 'nano-banana-pro');
        Config::set('content.cover_branding.enabled', true);
        Config::set('content.cover_branding.model', 'nano-banana-pro');
        Config::set('content.cover_branding.title_max_len', 70);

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function full_flow_manifest_upload_dispatch(): void
    {
        Bus::fake();
        $admin = User::factory()->create();

        // Pre-seed White House cache row (represents a successful prior plugin fetch)
        EntityReference::create([
            'qid' => 'Q35525',
            'name' => 'White House',
            'entity_type' => 'landmark',
            'local_path' => 'entity-refs/landmark/Q35525_white-house.jpg',
            'local_url' => 'http://localhost/storage/entity-refs/landmark/Q35525_white-house.jpg',
            'license' => 'PD-USGov',
            'attribution' => 'White House Photo Office',
            'source' => 'wikimedia',
            'fetched_at' => now(),
        ]);

        $idea = ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Anthropic CEO Visits the White House',
            'status' => 'article_ready',
            'priority' => 'medium',
            'auto_mode' => false,
            'generated_article' => [
                'language' => 'en',
                'en' => ['title' => 'Anthropic CEO Visits the White House'],
                'image_prompts' => [[
                    'type' => 'cover',
                    'prompt_text' => 'Dario Amodei walking into the West Wing',
                    'visual_direction' => 'Asian-American man in his 40s, navy suit',
                    'caption' => 'Anthropic CEO Visits the White House',
                    'entity_refs' => [
                        ['qid' => 'Q115468560', 'name' => 'Dario Amodei', 'entity_type' => 'person', 'url' => null],
                        ['qid' => 'Q35525', 'name' => 'White House', 'entity_type' => 'landmark', 'url' => 'http://localhost/storage/entity-refs/landmark/Q35525_white-house.jpg', 'license' => 'PD-USGov'],
                    ],
                ]],
            ],
        ]);

        $this->actingAs($admin, 'sanctum');

        // 1. Plugin posts manifest_needed (Dario missing — CC-BY-SA rejected by the plugin)
        $manifest = [
            'brand' => [],
            'entity' => [
                ['entity_name' => 'Dario Amodei', 'entity_type' => 'person', 'qid' => 'Q115468560', 'used_in' => ['Cover'], 'status' => 'missing', 'reason' => 'CC-BY-SA license not allowed', 'required' => true],
                ['entity_name' => 'White House', 'entity_type' => 'landmark', 'qid' => 'Q35525', 'used_in' => ['Cover'], 'status' => 'fetched', 'fetched_url' => 'http://localhost/storage/entity-refs/landmark/Q35525_white-house.jpg', 'license' => 'PD-USGov', 'required' => false],
            ],
        ];

        $this->putJson("/api/automation/content-ideas/{$idea->id}/progress", [
            'step' => 'manifest_needed',
            'percentage' => 20,
            'message' => 'entities detected',
            'manifest' => $manifest,
        ])->assertOk();

        Bus::assertDispatched(DispatchTelegramNotification::class, fn($j) => $j->contentIdeaId === $idea->id && $j->notificationType === 'manifest_needed');

        $idea->refresh();
        $this->assertSame('awaiting_manual_upload', $idea->status);
        $this->assertCount(2, $idea->pending_manifest['entity']);

        // 2. Admin uploads Dario reference
        $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/upload-entity-reference", [
            'entity_name' => 'Dario Amodei',
            'entity_type' => 'person',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('dario.jpg', 300, 'image/jpeg'),
        ])->assertOk();

        $idea->refresh();
        $this->assertSame('article_ready', $idea->status);
        $this->assertSame(2, EntityReference::count(), 'Upload should create user_upload row alongside pre-seeded wikimedia row');

        $darioRef = collect($idea->generated_article['image_prompts'][0]['entity_refs'])
            ->firstWhere('name', 'Dario Amodei');
        $this->assertNotNull($darioRef['url']);
        $this->assertStringContainsString('entity-refs/person/user_dario-amodei', $darioRef['url']);
        $this->assertSame('USER-UPLOADED', $darioRef['license']);

        // 3. Dispatch GeminiGen — mock Setting + Http for the dispatch pipeline
        Http::fake([
            'api.geminigen.ai/*' => Http::response(['uuid' => 'e2e-uuid-1', 'status' => 1], 200),
        ]);

        Storage::disk('public')->put('about/ali.png', 'fake-creator-bytes');
        Storage::disk('public')->put('branding/logo.png', 'fake-logo-bytes');

        $aboutQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $aboutQuery->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $aboutQuery->shouldReceive('value')->with('value')->andReturn('about/ali.png');

        $brandValues = [
            'watermark_enabled' => 'true',
            'creator_brand_logo' => 'branding/logo.png',
            'creator_brand_tagline' => 'alisadikinma.com',
            'watermark_opacity' => '0.30',
            'creator_brand_slug' => 'alisadikinma',
        ];
        $brandQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $brandQuery->shouldReceive('where')->andReturnUsing(function ($col, $key) use ($brandValues) {
            $stub = Mockery::mock('Illuminate\Database\Eloquent\Builder');
            $stub->shouldReceive('value')->with('value')->andReturn($brandValues[$key] ?? null);
            return $stub;
        });

        $settingMock = Mockery::mock('alias:' . Setting::class);
        $settingMock->shouldReceive('where')->with('group', 'about')->andReturn($aboutQuery);
        $settingMock->shouldReceive('where')->with('group', 'creator_brand')->andReturn($brandQuery);

        $jobMock = Mockery::mock('alias:' . ImageGenerationJob::class);
        $jobMock->shouldReceive('create')->andReturn(new ImageGenerationJob());
        $collisionQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $collisionQuery->shouldReceive('exists')->andReturn(false);
        $jobMock->shouldReceive('where')->with('planned_filename', Mockery::any())->andReturn($collisionQuery);

        $idea->refresh();
        app(ImageGenerationService::class)->triggerForIdea($idea);

        // 4. Assertions: correct file_urls composition
        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'api.geminigen.ai')) return false;
            $body = (string) $request->body();
            preg_match_all('/name="file_urls"[\s\S]*?\r\n\r\n([^\r\n]+)/', $body, $matches);
            $fileUrls = $matches[1] ?? [];

            $hasDario = collect($fileUrls)->contains(fn($u) => str_contains($u, 'entity-refs/person/user_dario-amodei'));
            $hasWhiteHouse = collect($fileUrls)->contains(fn($u) => str_contains($u, 'Q35525_white-house'));
            $hasWatermarkLogo = collect($fileUrls)->contains(fn($u) => str_contains($u, 'branding/logo.png'));
            $hasCreatorLeaked = collect($fileUrls)->contains(fn($u) => str_contains($u, '/about/ali.png'));

            return $hasDario && $hasWhiteHouse && $hasWatermarkLogo && !$hasCreatorLeaked;
        });

        // 5. Title overlay + model override still intact
        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'api.geminigen.ai')) return false;
            $body = (string) $request->body();
            return str_contains($body, 'Anthropic CEO Visits the White House')
                && str_contains($body, 'thumbnail-style title text')
                && str_contains($body, 'nano-banana-pro');
        });
    }
}
