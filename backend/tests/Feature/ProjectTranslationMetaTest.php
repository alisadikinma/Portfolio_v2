<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PF-2 2026-11-09: project pages read meta_title/meta_description from the API. The
 * resource returned only the base (English) row, so /id/projects/* had English <title>s.
 */
class ProjectTranslationMetaTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(): Project
    {
        $p = Project::create([
            'title' => 'AI Gift Box Counting', 'slug' => 'ai-gift-box-counting',
            'description' => 'EN desc', 'content' => '<p>en</p>',
            'meta_title' => 'EN meta title', 'meta_description' => 'EN meta description',
            'category' => 'AI', 'published' => true, 'is_active' => true,
        ]);
        $p->translations()->create([
            'language' => 'id', 'title' => 'AI Gift Box Counting', 'slug' => 'ai-gift-box-counting',
            'description' => 'ID desc', 'content' => '<p>id</p>',
            'meta_title' => 'ID meta title', 'meta_description' => 'ID meta description',
        ]);
        return $p;
    }

    public function test_id_request_gets_translated_meta(): void
    {
        $this->makeProject();
        $this->getJson('/api/projects/ai-gift-box-counting?lang=id')
            ->assertOk()
            ->assertJsonPath('data.meta_title', 'ID meta title')
            ->assertJsonPath('data.meta_description', 'ID meta description')
            ->assertJsonPath('data.description', 'ID desc');
    }

    public function test_en_request_keeps_base_meta(): void
    {
        $this->makeProject();
        $this->getJson('/api/projects/ai-gift-box-counting?lang=en')
            ->assertOk()
            ->assertJsonPath('data.meta_title', 'EN meta title')
            ->assertJsonPath('data.meta_description', 'EN meta description');
    }

    public function test_empty_translated_meta_falls_back_to_base(): void
    {
        $p = $this->makeProject();
        $p->translations()->where('language', 'id')->update(['meta_title' => null, 'meta_description' => '']);
        $this->getJson('/api/projects/ai-gift-box-counting?lang=id')
            ->assertJsonPath('data.meta_title', 'EN meta title')
            ->assertJsonPath('data.meta_description', 'EN meta description');
    }
}
