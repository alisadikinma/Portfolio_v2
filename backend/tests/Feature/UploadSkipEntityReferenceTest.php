<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\EntityReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadSkipEntityReferenceTest extends TestCase
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

        Storage::fake('public');
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function makeIdeaWithManifest(): ContentIdea
    {
        return ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Anthropic CEO Visits the White House',
            'status' => 'awaiting_manual_upload',
            'priority' => 'medium',
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Dario at White House',
                        'entity_refs' => [
                            // Placeholder entry from plugin when manifest was generated
                            [
                                'qid' => 'Q115468560',
                                'name' => 'Dario Amodei',
                                'entity_type' => 'person',
                                'url' => null,
                                'license' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'pending_manifest' => [
                'entity' => [
                    [
                        'entity_name' => 'Dario Amodei',
                        'entity_type' => 'person',
                        'qid' => 'Q115468560',
                        'used_in' => ['Cover'],
                        'status' => 'missing',
                        'required' => true,
                    ],
                ],
            ],
        ]);
    }

    /** @test */
    public function upload_saves_file_creates_entity_reference_row_and_patches_segments(): void
    {
        $idea = $this->makeIdeaWithManifest();

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/upload-entity-reference", [
            'entity_name' => 'Dario Amodei',
            'entity_type' => 'person',
            'file' => UploadedFile::fake()->create('dario.jpg', 200, 'image/jpeg'),
        ]);

        $response->assertOk();
        $this->assertSame(1, EntityReference::where('source', 'user_upload')->count());

        $idea->refresh();
        $coverRefs = $idea->generated_article['image_prompts'][0]['entity_refs'] ?? [];
        $this->assertCount(1, $coverRefs);
        $this->assertStringContainsString('entity-refs/person/user_dario-amodei', $coverRefs[0]['url']);
        $this->assertSame('USER-UPLOADED', $coverRefs[0]['license']);

        $this->assertSame('resolved', $idea->pending_manifest['entity'][0]['status']);
        // Status flips back from awaiting_manual_upload to article_ready
        $this->assertSame('article_ready', $idea->status);
    }

    /** @test */
    public function skip_removes_entity_from_segments_and_unblocks_status(): void
    {
        $idea = $this->makeIdeaWithManifest();

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/skip-entity-reference", [
            'entity_name' => 'Dario Amodei',
        ]);

        $response->assertOk();

        $idea->refresh();
        $coverRefs = $idea->generated_article['image_prompts'][0]['entity_refs'] ?? [];
        $this->assertCount(0, $coverRefs);

        $this->assertSame('skipped', $idea->pending_manifest['entity'][0]['status']);
        $this->assertSame('article_ready', $idea->status);
    }

    /** @test */
    public function upload_validates_entity_type(): void
    {
        $idea = $this->makeIdeaWithManifest();

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/upload-entity-reference", [
            'entity_name' => 'Dario',
            'entity_type' => 'invalid',
            'file' => UploadedFile::fake()->create('x.jpg', 200, 'image/jpeg'),
        ]);

        $response->assertStatus(422);
    }
}
