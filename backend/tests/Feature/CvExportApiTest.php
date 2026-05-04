<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for the CV Master Export API (Phase 10).
 *
 * Verifies:
 *   - Auth gate (no token → 401)
 *   - Ability gate (token without cv:read → 403)
 *   - Successful payload shape with cv:read ability
 *   - basics block sourced from settings group=about
 *   - projects ordered by sort_order
 *   - awards ordered by is_featured DESC, id DESC
 *   - thought_leadership returns ≤5 most recent published posts
 *   - schema_version + generated_at correctness
 */
class CvExportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Default APP_URL contains the XAMPP subpath which trips relative
        // route resolution under sqlite — same workaround other Feature
        // tests use.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
    }

    /** @test */
    public function returns_401_without_token(): void
    {
        $this->getJson('/api/cv/export')->assertStatus(401);
    }

    /** @test */
    public function returns_403_when_token_missing_cv_read_ability(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['some:other:ability']);

        $this->getJson('/api/cv/export')->assertStatus(403);
    }

    /** @test */
    public function returns_full_payload_shape_with_cv_read_token(): void
    {
        $this->seedAboutSettings();
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->getJson('/api/cv/export');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'schema_version',
                    'generated_at',
                    'basics' => [
                        'name',
                        'label',
                        'email',
                        'phone',
                        'url',
                        'summary',
                        'location' => ['city', 'country', 'remote'],
                        'profiles',
                    ],
                    'projects',
                    'awards',
                    'thought_leadership',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.schema_version', '1.0.0');
    }

    /** @test */
    public function basics_pulled_from_settings_group_about(): void
    {
        Setting::create(['group' => 'about', 'key' => 'name', 'value' => 'Custom Name']);
        Setting::create(['group' => 'about', 'key' => 'title', 'value' => 'Custom Headline']);
        Setting::create(['group' => 'about', 'key' => 'bio', 'value' => 'Bio summary text']);
        Setting::create([
            'group' => 'about',
            'key' => 'social_links',
            'value' => json_encode([
                ['platform' => 'LinkedIn', 'url' => 'https://linkedin.com/in/x', 'icon' => 'fab fa-linkedin'],
                ['platform' => 'NoUrl', 'icon' => 'fab fa-x'],  // dropped — no url
            ]),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->getJson('/api/cv/export');

        $response->assertOk()
            ->assertJsonPath('data.basics.name', 'Custom Name')
            ->assertJsonPath('data.basics.label', 'Custom Headline')
            ->assertJsonPath('data.basics.summary', 'Bio summary text')
            ->assertJsonPath('data.basics.email', null)
            ->assertJsonPath('data.basics.phone', null)
            ->assertJsonPath('data.basics.location.country', 'Indonesia');

        $profiles = $response->json('data.basics.profiles');
        $this->assertCount(1, $profiles);
        $this->assertSame('LinkedIn', $profiles[0]['network']);
        $this->assertSame('https://linkedin.com/in/x', $profiles[0]['url']);
    }

    /** @test */
    public function projects_ordered_by_sort_order(): void
    {
        Project::create([
            'title' => 'Z Project',
            'slug' => 'z-project',
            'description' => 'desc',
            'category' => 'web',
            'published' => true,
            'sort_order' => 30,
        ]);
        Project::create([
            'title' => 'A Project',
            'slug' => 'a-project',
            'description' => 'desc',
            'category' => 'web',
            'published' => true,
            'sort_order' => 10,
        ]);
        Project::create([
            'title' => 'M Project',
            'slug' => 'm-project',
            'description' => 'desc',
            'category' => 'web',
            'published' => true,
            'sort_order' => 20,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->getJson('/api/cv/export');

        $response->assertOk();
        $names = collect($response->json('data.projects'))->pluck('name')->all();
        $this->assertSame(['A Project', 'M Project', 'Z Project'], $names);
    }

    /** @test */
    public function awards_ordered_by_is_featured_desc_then_id_desc(): void
    {
        Award::create([
            'title' => 'Old Unfeatured',
            'organization' => 'Foo',
            'received_at' => '2018',
            'is_featured' => false,
        ]);
        Award::create([
            'title' => 'Recent Unfeatured',
            'organization' => 'Bar',
            'received_at' => '2024',
            'is_featured' => false,
        ]);
        Award::create([
            'title' => 'Featured Outskill',
            'organization' => 'Outskill',
            'received_at' => '2025',
            'is_featured' => true,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->getJson('/api/cv/export');

        $response->assertOk();
        $titles = collect($response->json('data.awards'))->pluck('title')->all();
        // Featured row wins regardless of insertion id; remaining rows sorted by id desc
        $this->assertSame(
            ['Featured Outskill', 'Recent Unfeatured', 'Old Unfeatured'],
            $titles
        );
        $this->assertTrue($response->json('data.awards.0.is_featured'));
    }

    /** @test */
    public function thought_leadership_returns_five_most_recent_published_posts(): void
    {
        $cat = Category::create(['name' => 'AI', 'slug' => 'ai']);

        // 7 published posts — only the 5 most recent should land in the payload.
        for ($i = 1; $i <= 7; $i++) {
            $post = Post::create([
                'category_id' => $cat->id,
                'slug' => "post-$i",
                'title' => "Stub Title $i",
                'excerpt' => "Stub excerpt $i",
                'content' => "<p>Body $i</p>",
                'published' => true,
                'published_at' => now()->subDays(8 - $i), // post-7 is most recent
            ]);
            PostTranslation::create([
                'post_id' => $post->id,
                'language' => 'id',
                'title' => "ID Title $i",
                'slug' => "post-$i",
                'excerpt' => "ID excerpt $i",
                'content' => "<p>ID Body $i</p>",
            ]);
        }

        // 1 unpublished — must NOT appear
        Post::create([
            'category_id' => $cat->id,
            'slug' => 'unpublished',
            'title' => 'Unpublished',
            'excerpt' => 'No',
            'content' => '<p>No</p>',
            'published' => false,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->getJson('/api/cv/export');

        $response->assertOk();
        $items = $response->json('data.thought_leadership');
        $this->assertCount(5, $items);
        // Most recent (post-7) is first
        $this->assertSame('ID Title 7', $items[0]['title']);
        $this->assertSame('ID Title 3', $items[4]['title']);
    }

    /** @test */
    public function thought_leadership_returns_fewer_than_five_when_inventory_short(): void
    {
        $cat = Category::create(['name' => 'AI', 'slug' => 'ai']);
        $post = Post::create([
            'category_id' => $cat->id,
            'slug' => 'only-post',
            'title' => 'Only',
            'excerpt' => 'x',
            'content' => '<p>x</p>',
            'published' => true,
            'published_at' => now()->subHour(),
        ]);
        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'id',
            'title' => 'Hanya Satu',
            'slug' => 'only-post',
            'excerpt' => 'x',
            'content' => '<p>x</p>',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->getJson('/api/cv/export');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.thought_leadership'));
    }

    /** @test */
    public function schema_version_is_one_dot_zero_dot_zero(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $this->getJson('/api/cv/export')
            ->assertOk()
            ->assertJsonPath('data.schema_version', '1.0.0');
    }

    /** @test */
    public function generated_at_is_valid_iso8601_zulu_string(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->getJson('/api/cv/export');

        $response->assertOk();
        $generatedAt = $response->json('data.generated_at');
        $this->assertIsString($generatedAt);
        // ISO-8601 Z form: 2026-05-04T12:34:56Z
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $generatedAt
        );
        // Round-trip parse must succeed
        $parsed = \Carbon\Carbon::parse($generatedAt);
        $this->assertTrue($parsed->isUtc());
    }

    /**
     * Seed a minimal but realistic about-settings row set so the basics
     * block looks like production. Used by the headline shape test.
     */
    protected function seedAboutSettings(): void
    {
        Setting::create(['group' => 'about', 'key' => 'name', 'value' => 'Ali Sadikin Ma']);
        Setting::create(['group' => 'about', 'key' => 'title', 'value' => 'AI Generalist Expert']);
        Setting::create(['group' => 'about', 'key' => 'bio', 'value' => 'Bio summary.']);
    }
}
