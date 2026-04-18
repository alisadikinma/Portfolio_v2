<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\Setting;
use App\Services\CoverBrandingEnhancer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CoverBrandingEnhancerTest extends TestCase
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

    private function mockSetting(?string $value): void
    {
        // Mock Setting::where('group','about')->where('key','profile_photo')->value('value')
        $query = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $query->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $query->shouldReceive('value')->with('value')->andReturn($value);

        // creator_brand group — default: watermark disabled (matches seeded default),
        // all other keys resolve to null so appendWatermark is a no-op in these tests.
        $brandQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $brandQuery->shouldReceive('where')->andReturnUsing(function ($col, $key) {
            $stub = Mockery::mock('Illuminate\Database\Eloquent\Builder');
            $stub->shouldReceive('value')->with('value')->andReturn(null);
            return $stub;
        });

        $mock = Mockery::mock('alias:' . Setting::class);
        $mock->shouldReceive('where')->with('group', 'about')->andReturn($query);
        $mock->shouldReceive('where')->with('group', 'creator_brand')->andReturn($brandQuery);
    }

    private function makeIdea(array $override = []): ContentIdea
    {
        $idea = new ContentIdea();
        $idea->title = $override['title'] ?? 'Default Title';
        $idea->generated_article = $override['generated_article'] ?? [
            'language' => 'id',
            'id' => ['title' => $override['title'] ?? 'Default Title'],
        ];
        return $idea;
    }

    /** @test */
    public function non_cover_prompt_passes_through_unchanged()
    {
        $prompt = [
            'type' => 'inline',
            'prompt_text' => 'A diagram of data flow',
            'visual_direction' => 'abstract illustration',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting('about/ali.png');
        $idea = $this->makeIdea();

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertSame($prompt, $result);
    }

    /** @test */
    public function cover_prompt_gets_title_instruction_appended()
    {
        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A futuristic office scene',
            'visual_direction' => 'modern UI dashboard abstract',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting(null);
        $idea = $this->makeIdea(['title' => 'How AI Changes Everything']);

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertStringContainsString('How AI Changes Everything', $result['prompt_text']);
        $this->assertStringContainsString('thumbnail-style title text', $result['prompt_text']);
        $this->assertStringStartsWith('A futuristic office scene', $result['prompt_text']);
    }

    /** @test */
    public function cover_overlay_uses_caption_when_present_not_article_title()
    {
        // Regression: the user-editable caption field must drive the big
        // thumbnail-style overlay rendered into the image. Previously the
        // enhancer always used resolveTitle($idea), so users who edited the
        // caption in the admin UI saw no change on regenerate.
        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A scene',
            'visual_direction' => 'abstract',
            'caption' => '15 examples of what Gemini 3 can do',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting(null);
        $idea = $this->makeIdea(['title' => 'Completely Different Article Title']);

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertStringContainsString('15 examples of what Gemini 3 can do', $result['prompt_text']);
        $this->assertStringNotContainsString('Completely Different Article Title', $result['prompt_text']);
    }

    /** @test */
    public function cover_overlay_falls_back_to_article_title_when_caption_empty()
    {
        // When caption is blank/whitespace/missing, overlay must still render —
        // fall through to resolveTitle($idea) so cold-start ideas aren't blank.
        foreach ([null, '', '   '] as $emptyCaption) {
            $prompt = [
                'type' => 'cover',
                'prompt_text' => 'A scene',
                'visual_direction' => 'abstract',
                'caption' => $emptyCaption,
                'face_refs' => [],
                'model' => 'nano-banana-2',
            ];

            $this->mockSetting(null);
            $idea = $this->makeIdea(['title' => 'Fallback Article Title']);

            $result = $this->enhancer->enhance($prompt, $idea);

            $this->assertStringContainsString('Fallback Article Title', $result['prompt_text']);
        }
    }

    /** @test */
    public function cover_prompt_with_human_keyword_prepends_creator_face_url()
    {
        Storage::disk('public')->put('about/ali.png', 'fake-image-data');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A scene',
            'visual_direction' => 'portrait of creator at desk',
            'face_refs' => ['https://example.com/existing-ref.jpg'],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting('about/ali.png');
        $idea = $this->makeIdea();

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertCount(2, $result['face_refs']);
        $this->assertStringContainsString('about/ali.png', $result['face_refs'][0]);
        $this->assertSame('https://example.com/existing-ref.jpg', $result['face_refs'][1]);
    }

    /** @test */
    public function cover_prompt_without_human_keyword_does_not_modify_face_refs()
    {
        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'Modern dashboard UI',
            'visual_direction' => 'abstract glowing monitors, cinematic',
            'face_refs' => ['https://example.com/existing.jpg'],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting('about/ali.png');
        $idea = $this->makeIdea();

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertSame(['https://example.com/existing.jpg'], $result['face_refs']);
    }

    /** @test */
    public function cover_prompt_always_forces_model_nano_banana_pro()
    {
        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A scene',
            'visual_direction' => 'abstract',
            'face_refs' => [],
            'model' => 'imagen-4',
        ];

        $this->mockSetting(null);
        $idea = $this->makeIdea();

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertSame('nano-banana-pro', $result['model']);
    }

    /** @test */
    public function enabled_false_returns_prompt_unchanged()
    {
        Config::set('content.cover_branding.enabled', false);

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A scene with a person',
            'visual_direction' => 'portrait of creator',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $idea = $this->makeIdea();

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertSame($prompt, $result);
    }

    /** @test */
    public function missing_profile_photo_logs_warning_and_continues()
    {
        Log::shouldReceive('warning')->once();

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A scene',
            'visual_direction' => 'portrait of creator',
            'face_refs' => ['https://example.com/existing.jpg'],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting(null);
        $idea = $this->makeIdea(['title' => 'Test']);

        $result = $this->enhancer->enhance($prompt, $idea);

        // face_refs unchanged (no creator prepended)
        $this->assertSame(['https://example.com/existing.jpg'], $result['face_refs']);
        // Title still injected
        $this->assertStringContainsString('Test', $result['prompt_text']);
    }

    /** @test */
    public function title_exceeding_max_len_is_truncated_with_ellipsis()
    {
        Config::set('content.cover_branding.title_max_len', 70);

        $longTitle = str_repeat('A', 100);
        $prompt = [
            'type' => 'cover',
            'prompt_text' => '',
            'visual_direction' => 'abstract',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting(null);
        $idea = $this->makeIdea(['title' => $longTitle]);

        $result = $this->enhancer->enhance($prompt, $idea);

        // Title in prompt must be exactly 70 chars, ending with ...
        $this->assertMatchesRegularExpression('/"A{67}\.\.\."/', $result['prompt_text']);
    }

    /** @test */
    public function title_with_emoji_has_emoji_stripped()
    {
        $prompt = [
            'type' => 'cover',
            'prompt_text' => '',
            'visual_direction' => 'abstract',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting(null);
        $idea = $this->makeIdea(['title' => 'Hello 🚀 World ✨']);

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertStringNotContainsString('🚀', $result['prompt_text']);
        $this->assertStringNotContainsString('✨', $result['prompt_text']);
        $this->assertStringContainsString('Hello', $result['prompt_text']);
        $this->assertStringContainsString('World', $result['prompt_text']);
    }

    /** @test */
    public function indonesian_keyword_manusia_triggers_face_inject()
    {
        Storage::disk('public')->put('about/ali.png', 'fake-image-data');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A scene',
            'visual_direction' => 'potret manusia modern di kantor',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $this->mockSetting('about/ali.png');
        $idea = $this->makeIdea();

        $result = $this->enhancer->enhance($prompt, $idea);

        $this->assertCount(1, $result['face_refs']);
        $this->assertStringContainsString('about/ali.png', $result['face_refs'][0]);
    }
}
