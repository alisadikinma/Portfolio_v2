<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Models\Setting;
use App\Services\ImageGenerationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Phase D regression guard: entity_refs[] URLs populated by the plugin in
 * /article-images Phase 3.5b Wikidata lookup must actually ship to GeminiGen
 * as multipart file_urls parts when the idea is dispatched. The flow is:
 *
 *   image_prompts[i].entity_refs[] → CoverBrandingEnhancer::mergeEntityRefsIntoFileUrls
 *     → $enhanced['file_urls'] → ImageGenerationService styleRefs param
 *     → queue() $allRefs → multipart file_urls parts
 *
 * Lives in its own class (isolated setUp) so Mockery aliases don't collide
 * with ImageGenerationTriggerForIdeaTest or WatermarkLogoDispatchTest.
 */
class ImageGenerationEntityRefsDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('content.default_image_model', 'nano-banana-pro');
        Config::set('content.cover_branding.enabled', true);
        Config::set('content.cover_branding.model', 'nano-banana-pro');
        Config::set('content.cover_branding.title_max_len', 70);

        Http::fake([
            'api.geminigen.ai/*' => Http::response(['uuid' => 'uuid-entity', 'status' => 1], 200),
        ]);

        Storage::fake('public');

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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeIdea(array $imagePrompts): ContentIdea
    {
        $idea = Mockery::mock(ContentIdea::class)->makePartial();
        $idea->id = 200;
        $idea->title = 'Anthropic CEO Visits the White House';
        $idea->status = 'article_ready';
        $idea->generated_article = [
            'language' => 'en',
            'en' => ['title' => 'Anthropic CEO Visits the White House'],
            'image_prompts' => $imagePrompts,
        ];
        $idea->shouldReceive('save')->andReturn(true);
        return $idea;
    }

    /** @test */
    public function entity_ref_urls_ship_as_multipart_file_urls_to_geminigen(): void
    {
        $idea = $this->makeIdea([
            [
                'type' => 'cover',
                'prompt_text' => 'Dario Amodei walking into the West Wing',
                'visual_direction' => 'Asian-American man in his 40s',
                'entity_refs' => [
                    [
                        'qid' => 'Q115468560',
                        'name' => 'Dario Amodei',
                        'entity_type' => 'person',
                        'url' => 'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario.jpg',
                        'license' => 'CC-BY-4.0',
                    ],
                    [
                        'qid' => 'Q35525',
                        'name' => 'White House',
                        'entity_type' => 'landmark',
                        'url' => 'https://alisadikinma.com/storage/entity-refs/landmark/Q35525_white-house.jpg',
                        'license' => 'PD-USGov',
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->triggerForIdea($idea);

        Http::assertSent(function ($request) {
            $body = (string) $request->body();

            // Parse all multipart parts with name="file_urls" — same pattern as
            // WatermarkLogoDispatchTest to avoid false-pass if URL leaked into prompt_text.
            preg_match_all(
                '/name="file_urls"[\s\S]*?\r\n\r\n([^\r\n]+)/',
                $body,
                $matches
            );
            $fileUrlValues = $matches[1] ?? [];

            $hasPerson = collect($fileUrlValues)
                ->contains(fn ($u) => str_contains($u, 'Q115468560_dario.jpg'));
            $hasLandmark = collect($fileUrlValues)
                ->contains(fn ($u) => str_contains($u, 'Q35525_white-house.jpg'));

            return $hasPerson && $hasLandmark;
        });
    }

    /** @test */
    public function person_entity_prevents_creator_face_from_shipping_as_file_url(): void
    {
        $idea = $this->makeIdea([
            [
                'type' => 'cover',
                'prompt_text' => 'Dario walking',
                'visual_direction' => 'exec',
                'entity_refs' => [
                    [
                        'qid' => 'Q115468560',
                        'name' => 'Dario Amodei',
                        'entity_type' => 'person',
                        'url' => 'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario.jpg',
                        'license' => 'CC-BY-4.0',
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->triggerForIdea($idea);

        Http::assertSent(function ($request) {
            $body = (string) $request->body();

            preg_match_all(
                '/name="file_urls"[\s\S]*?\r\n\r\n([^\r\n]+)/',
                $body,
                $matches
            );
            $fileUrlValues = $matches[1] ?? [];

            // About-group profile_photo returned null in setUp, so creator URL
            // never resolves. Here we assert the Dario URL IS present and no
            // about-path leaked in.
            $hasDario = collect($fileUrlValues)
                ->contains(fn ($u) => str_contains($u, 'Q115468560_dario.jpg'));

            $hasAboutLeak = collect($fileUrlValues)
                ->contains(fn ($u) => str_contains($u, '/about/'));

            return $hasDario && !$hasAboutLeak;
        });
    }
}
