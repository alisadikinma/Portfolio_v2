<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\Setting;
use App\Services\CoverBrandingEnhancer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CoverBrandingEntityGateTest extends TestCase
{
    private CoverBrandingEnhancer $enhancer;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('content.cover_branding.enabled', true);
        Config::set('content.cover_branding.model', 'nano-banana-pro');
        Config::set('content.cover_branding.title_max_len', 70);

        Storage::fake('public');

        $this->enhancer = new CoverBrandingEnhancer();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockSettings(?string $profilePhoto = 'about/ali.png'): void
    {
        $aboutQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $aboutQuery->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $aboutQuery->shouldReceive('value')->with('value')->andReturn($profilePhoto);

        $brandQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $brandQuery->shouldReceive('where')->andReturnUsing(function ($col, $key) {
            $stub = Mockery::mock('Illuminate\Database\Eloquent\Builder');
            $stub->shouldReceive('value')->with('value')->andReturn(null);
            return $stub;
        });

        $mock = Mockery::mock('alias:' . Setting::class);
        $mock->shouldReceive('where')->with('group', 'about')->andReturn($aboutQuery);
        $mock->shouldReceive('where')->with('group', 'creator_brand')->andReturn($brandQuery);
    }

    private function makeIdea(): ContentIdea
    {
        $idea = new ContentIdea();
        $idea->title = 'Anthropic CEO Visits the White House';
        $idea->generated_article = [
            'language' => 'en',
            'en' => ['title' => 'Anthropic CEO Visits the White House'],
        ];
        return $idea;
    }

    /** @test */
    public function cover_with_person_entity_ref_skips_creator_face_prepend(): void
    {
        $this->mockSettings('about/ali.png');
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'Dario Amodei walking into the West Wing',
            'visual_direction' => 'Asian-American man in his 40s, short dark hair, navy suit',
            'face_refs' => [],
            'entity_refs' => [
                [
                    'qid' => 'Q115468560',
                    'name' => 'Dario Amodei',
                    'entity_type' => 'person',
                    'url' => 'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario-amodei.jpg',
                    'license' => 'CC-BY-4.0',
                ],
            ],
            'caption' => 'Anthropic CEO Visits the White House',
        ];

        $result = $this->enhancer->enhance($prompt, $this->makeIdea());

        // Creator face must NOT be prepended (entity gate fires).
        $creatorUrl = url('/storage/about/ali.png');
        $this->assertNotContains($creatorUrl, $result['face_refs'] ?? []);

        // Person entity URL must be promoted to face_refs — that's the
        // channel GeminiGen's queue() uses to emit the "maintain exact
        // facial identity" prompt directive. Previously this URL went
        // to file_urls (environment/style channel) which made GeminiGen
        // ignore the identity reference. Symptom: cover of "Anthropic
        // CEO Visits White House" rendered with a generic exec face
        // instead of Dario Amodei's actual Wikimedia photo.
        $this->assertContains(
            'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario-amodei.jpg',
            $result['face_refs'] ?? []
        );
        // And NOT in file_urls (person URLs deliberately skip that bucket).
        $this->assertNotContains(
            'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario-amodei.jpg',
            $result['file_urls'] ?? []
        );

        // VD must NOT have been rewritten (visual_direction_original not set)
        $this->assertArrayNotHasKey('visual_direction_original', $result);
        $this->assertSame(
            'Asian-American man in his 40s, short dark hair, navy suit',
            $result['visual_direction']
        );
    }

    /** @test */
    public function cover_without_entity_refs_still_prepends_creator_face_regression_guard(): void
    {
        $this->mockSettings('about/ali.png');
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A generic developer scene',
            'visual_direction' => 'professional person coding at desk',
            'face_refs' => [],
            // NO entity_refs
        ];

        $result = $this->enhancer->enhance($prompt, $this->makeIdea());

        $creatorUrl = url('/storage/about/ali.png');
        $this->assertContains($creatorUrl, $result['face_refs'] ?? []);
    }

    /** @test */
    public function cover_with_only_landmark_entity_still_prepends_creator_face(): void
    {
        // When subject is Ali visiting a landmark (no person entity),
        // creator face SHOULD still be injected — landmark is just context.
        $this->mockSettings('about/ali.png');
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A visit to the Capitol building',
            'visual_direction' => 'developer at iconic location',
            'face_refs' => [],
            'entity_refs' => [
                [
                    'qid' => 'Q11768',
                    'name' => 'United States Capitol',
                    'entity_type' => 'landmark',
                    'url' => 'https://alisadikinma.com/storage/entity-refs/landmark/Q11768_capitol.jpg',
                    'license' => 'PD-USGov',
                ],
            ],
        ];

        $result = $this->enhancer->enhance($prompt, $this->makeIdea());

        $creatorUrl = url('/storage/about/ali.png');
        $this->assertContains($creatorUrl, $result['face_refs'] ?? []);
        // Landmark URL also merged into file_urls
        $this->assertContains(
            'https://alisadikinma.com/storage/entity-refs/landmark/Q11768_capitol.jpg',
            $result['file_urls'] ?? []
        );
    }

    /** @test */
    public function cover_with_person_and_landmark_routes_urls_to_correct_channels(): void
    {
        $this->mockSettings('about/ali.png');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'Dario at the White House',
            'visual_direction' => 'exec at government building',
            'face_refs' => [],
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
        ];

        $result = $this->enhancer->enhance($prompt, $this->makeIdea());

        $creatorUrl = url('/storage/about/ali.png');
        $this->assertNotContains($creatorUrl, $result['face_refs'] ?? []);

        // Person URL → face_refs (identity channel — triggers "maintain
        // exact facial identity" prompt directive in queue())
        $this->assertContains(
            'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario.jpg',
            $result['face_refs'] ?? []
        );
        $this->assertNotContains(
            'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario.jpg',
            $result['file_urls'] ?? []
        );

        // Landmark URL → file_urls (style/environment channel — landmark
        // is context, not identity; queue() emits the "maintain visual
        // consistency with environment" prompt directive for this bucket)
        $this->assertContains(
            'https://alisadikinma.com/storage/entity-refs/landmark/Q35525_white-house.jpg',
            $result['file_urls'] ?? []
        );
        $this->assertNotContains(
            'https://alisadikinma.com/storage/entity-refs/landmark/Q35525_white-house.jpg',
            $result['face_refs'] ?? []
        );
    }

    /** @test */
    public function cover_with_person_entity_still_applies_title_instruction_and_model_override(): void
    {
        $this->mockSettings('about/ali.png');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'Dario walking',
            'visual_direction' => 'exec at government building',
            'face_refs' => [],
            'entity_refs' => [
                [
                    'qid' => 'Q115468560',
                    'name' => 'Dario Amodei',
                    'entity_type' => 'person',
                    'url' => 'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario.jpg',
                    'license' => 'CC-BY-4.0',
                ],
            ],
            'caption' => 'Anthropic CEO Visits the White House',
        ];

        $result = $this->enhancer->enhance($prompt, $this->makeIdea());

        // Title overlay instruction still injected
        $this->assertStringContainsString(
            'Anthropic CEO Visits the White House',
            $result['prompt_text']
        );
        $this->assertStringContainsString('thumbnail-style title text', $result['prompt_text']);

        // Model override still applied
        $this->assertSame('nano-banana-pro', $result['model']);
    }

    /** @test */
    public function person_entity_url_is_deduped_in_face_refs(): void
    {
        $this->mockSettings('about/ali.png');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'scene',
            'visual_direction' => 'exec',
            // Person URL already present in face_refs (plugin pre-populated).
            // Enhancer must not duplicate it when promoting from entity_refs.
            'face_refs' => [
                'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario.jpg',
            ],
            'entity_refs' => [
                [
                    'qid' => 'Q115468560',
                    'name' => 'Dario Amodei',
                    'entity_type' => 'person',
                    'url' => 'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario.jpg',
                    'license' => 'CC-BY-4.0',
                ],
            ],
        ];

        $result = $this->enhancer->enhance($prompt, $this->makeIdea());

        $count = count(array_filter(
            $result['face_refs'] ?? [],
            fn($u) => $u === 'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario.jpg'
        ));
        $this->assertSame(1, $count, 'Person entity URL should appear exactly once in face_refs');
    }
}
