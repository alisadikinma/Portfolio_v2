<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Project;
use App\Models\Setting;
use Database\Seeders\BlogPromoSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BlogPromoSlotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL in .env includes /Portfolio_v2/backend/public subpath, which
        // makes getJson('/api/...') in tests build a URL Laravel can't route.
        // Strip the subpath for this test case (same pattern as
        // EntityRefsLookupEndpointTest).
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        $this->seed(BlogPromoSettingsSeeder::class);
        Cache::forget('blog_promo_slot');
    }

    protected function tearDown(): void
    {
        Cache::forget('blog_promo_slot');
        parent::tearDown();
    }

    /** @test */
    public function returns_pinned_project_when_setting_is_populated(): void
    {
        $pinned = Project::create([
            'title' => 'Pinned Showcase',
            'slug' => 'pinned-showcase',
            'description' => 'The operator-picked case study.',
            'image' => 'projects/pinned.jpg',
            'category' => 'web',
            'published' => true,
            'featured' => false,
        ]);

        Project::create([
            'title' => 'Some Other Featured',
            'slug' => 'some-other-featured',
            'description' => 'Another featured.',
            'category' => 'web',
            'featured' => true,
            'published' => true,
        ]);

        Setting::where('group', 'blog')
            ->where('key', 'blog_promo_project_id')
            ->update(['value' => (string) $pinned->id]);

        $response = $this->getJson('/api/blog/promo-slot');

        $response->assertOk()
            ->assertJsonPath('data.type', 'project')
            ->assertJsonPath('data.title', 'Pinned Showcase')
            ->assertJsonPath('data.link', '/projects/pinned-showcase')
            ->assertJsonPath('data.cta_label', 'Read case study')
            ->assertJsonPath('data.image', 'http://localhost/storage/projects/pinned.jpg');
    }

    /** @test */
    public function falls_back_to_latest_featured_project_when_no_pin(): void
    {
        Project::create([
            'title' => 'Not Featured',
            'slug' => 'not-featured',
            'description' => 'Plain project.',
            'category' => 'web',
            'featured' => false,
            'published' => true,
        ]);

        $featured = Project::create([
            'title' => 'Featured Hero',
            'slug' => 'featured-hero',
            'description' => 'The lead project.',
            'category' => 'web',
            'featured' => true,
            'published' => true,
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/blog/promo-slot');

        $response->assertOk()
            ->assertJsonPath('data.type', 'project')
            ->assertJsonPath('data.title', 'Featured Hero');
    }

    /** @test */
    public function falls_back_to_latest_award_when_no_projects_exist(): void
    {
        Award::create([
            'title' => 'Old Award',
            'organization' => 'Something',
            'image' => null,
            'received_at' => '2020-01-01',
            'sort_order' => 0,
        ]);
        Award::create([
            'title' => 'Recent Award',
            'description' => 'Got this recently.',
            'organization' => 'Jury',
            'image' => 'awards/recent.jpg',
            'received_at' => '2026-04-01',
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/blog/promo-slot');

        $response->assertOk()
            ->assertJsonPath('data.type', 'award')
            ->assertJsonPath('data.title', 'Recent Award')
            ->assertJsonPath('data.link', '/awards');
    }

    /** @test */
    public function falls_back_to_generic_cta_when_no_project_or_award(): void
    {
        $response = $this->getJson('/api/blog/promo-slot');

        $response->assertOk()
            ->assertJsonPath('data.type', 'cta')
            ->assertJsonStructure(['data' => ['type', 'title', 'description', 'link', 'cta_label']]);
    }

    /** @test */
    public function response_caches_for_a_minute(): void
    {
        Award::create([
            'title' => 'Cached Award',
            'organization' => 'Cache Co',
            'received_at' => '2026-01-01',
            'sort_order' => 0,
        ]);

        $first = $this->getJson('/api/blog/promo-slot');
        $first->assertJsonPath('data.title', 'Cached Award');

        // Mutate underlying data — but cache should still return first payload
        Award::where('title', 'Cached Award')->update(['title' => 'Changed Title']);

        $second = $this->getJson('/api/blog/promo-slot');
        $second->assertJsonPath('data.title', 'Cached Award');
    }
}
