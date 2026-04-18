<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Models\Setting;
use App\Services\ImageGenerationService;
use Database\Seeders\CreatorBrandSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandedFilenameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CreatorBrandSettingsSeeder::class);
    }

    private function makeIdeaWithKeyword(string $keyword, int $id = 42): ContentIdea
    {
        $idea = new ContentIdea();
        $idea->id = $id;
        $idea->title = 'Demo article';
        $idea->generated_article = [
            'prep_data' => [
                'research' => ['keyword' => $keyword],
            ],
        ];
        return $idea;
    }

    /** @test */
    public function buildBrandedFilename_uses_brand_slug_keyword_and_segment_label(): void
    {
        $idea = $this->makeIdeaWithKeyword('Vibe Coding Tools');
        $service = new ImageGenerationService();

        $this->assertSame(
            'alisadikinma-vibe-coding-tools-cover.png',
            $service->buildBrandedFilename($idea, 0)
        );
        $this->assertSame(
            'alisadikinma-vibe-coding-tools-body-1.png',
            $service->buildBrandedFilename($idea, 1)
        );
        $this->assertSame(
            'alisadikinma-vibe-coding-tools-body-2.png',
            $service->buildBrandedFilename($idea, 2)
        );
    }

    /** @test */
    public function buildBrandedFilename_respects_custom_brand_slug(): void
    {
        Setting::where('group', 'creator_brand')->where('key', 'creator_brand_slug')->update(['value' => 'acme-co']);

        $idea = $this->makeIdeaWithKeyword('AI Automation Guide');
        $service = new ImageGenerationService();

        $this->assertSame(
            'acme-co-ai-automation-guide-cover.png',
            $service->buildBrandedFilename($idea, 0)
        );
    }

    /** @test */
    public function buildBrandedFilename_falls_back_to_title_when_keyword_missing(): void
    {
        $idea = new ContentIdea();
        $idea->id = 99;
        $idea->title = 'How Developers Ship Fast';
        $idea->generated_article = []; // no prep_data

        $service = new ImageGenerationService();

        $this->assertSame(
            'alisadikinma-how-developers-ship-fast-cover.png',
            $service->buildBrandedFilename($idea, 0)
        );
    }

    /** @test */
    public function buildBrandedFilename_appends_suffix_on_collision(): void
    {
        $idea = $this->makeIdeaWithKeyword('Vibe Coding Tools');
        $service = new ImageGenerationService();

        // Pre-seed an existing job with the baseline filename
        ImageGenerationJob::create([
            'uuid' => 'existing-uuid',
            'type' => 'hero',
            'prompt' => 'x',
            'status' => 'completed',
            'planned_filename' => 'alisadikinma-vibe-coding-tools-cover.png',
        ]);

        $this->assertSame(
            'alisadikinma-vibe-coding-tools-cover-v2.png',
            $service->buildBrandedFilename($idea, 0)
        );
    }
}
