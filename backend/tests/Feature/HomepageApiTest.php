<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Project;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage for Phase 1 of the homepage redesign:
 *   - GET /api/homepage/stats shape
 *   - GET /api/homepage/featured shape + LinkedIn-only testimonial filter
 *   - SetLocaleByGeoIP precedence (cookie > CF-IPCountry > ip-api > 'en')
 */
class HomepageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default APP_URL contains the XAMPP subpath which breaks getJson()
        // routing under sqlite — same workaround the other Feature tests use.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // Block accidental real network calls. Individual tests fake what
        // they actually need.
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response('', 200),
        ]);
    }

    /** @test */
    public function stats_endpoint_returns_expected_shape(): void
    {
        Award::create([
            'title' => 'Test Award',
            'organization' => 'Outskill',
            'received_at' => '2025-01-01',
        ]);
        Project::create([
            'title' => 'Test Project',
            'slug' => 'test-project',
            'description' => 'desc',
            'category' => 'web',
            'published' => true,
        ]);

        $response = $this->getJson('/api/homepage/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'years_experience',
                    'awards_count',
                    'projects_count',
                    'enterprise_brands',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.awards_count', 1)
            ->assertJsonPath('data.projects_count', 1)
            ->assertJsonPath('data.years_experience', 17);

        $brands = $response->json('data.enterprise_brands');
        $this->assertIsArray($brands);
        $this->assertCount(6, $brands);
        $this->assertSame('Outskill', $brands[0]);
    }

    /** @test */
    public function featured_endpoint_returns_all_five_keys(): void
    {
        // Seed minimum data for each list
        Award::create([
            'title' => 'Featured Award',
            'organization' => 'Telkomsel',
            'received_at' => '2025-01-01',
            'is_featured' => true,
        ]);

        Testimonial::create([
            'client_name' => 'Jane Doe',
            'company_name' => 'Acme',
            'job_title' => 'CTO',
            'testimonial_text' => 'Great work.',
            'source' => 'linkedin',
            'source_url' => 'https://linkedin.com/feed/test',
        ]);

        Project::create([
            'title' => 'Featured Project',
            'slug' => 'featured-project',
            'description' => 'desc',
            'category' => 'web',
            'published' => true,
        ]);

        $cat = Category::create(['name' => 'AI', 'slug' => 'ai']);
        // posts.title / content are NOT NULL in the original migration even
        // though production drift now treats them as legacy (the canonical
        // values live in post_translations). Provide stub strings so SQLite
        // accepts the insert.
        $post = Post::create([
            'category_id' => $cat->id,
            'slug' => 'sample-post',
            'title' => 'Sample Post',
            'excerpt' => 'Excerpt here.',
            'content' => '<p>Body</p>',
            'published' => true,
            'published_at' => now()->subHour(),
        ]);
        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'en',
            'title' => 'Sample Post',
            'slug' => 'sample-post',
            'excerpt' => 'Excerpt here.',
            'content' => '<p>Body</p>',
        ]);

        $response = $this->getJson('/api/homepage/featured');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stats' => [
                        'years_experience',
                        'awards_count',
                        'projects_count',
                        'enterprise_brands',
                    ],
                    'featured_awards',
                    'featured_testimonials',
                    'featured_projects',
                    'latest_articles',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.featured_awards')
            ->assertJsonCount(1, 'data.featured_testimonials')
            ->assertJsonCount(1, 'data.featured_projects')
            ->assertJsonCount(1, 'data.latest_articles');
    }

    /** @test */
    public function featured_testimonials_filters_to_linkedin_source_only(): void
    {
        Testimonial::create([
            'client_name' => 'LinkedIn Person',
            'testimonial_text' => 'On LinkedIn.',
            'source' => 'linkedin',
        ]);
        Testimonial::create([
            'client_name' => 'Direct Person',
            'testimonial_text' => 'Sent direct.',
            'source' => 'direct',
        ]);
        Testimonial::create([
            'client_name' => 'Video Person',
            'testimonial_text' => 'Recorded.',
            'source' => 'video',
        ]);

        $response = $this->getJson('/api/homepage/featured');

        $response->assertOk();
        $items = $response->json('data.featured_testimonials');
        $this->assertCount(1, $items);
        $this->assertSame('LinkedIn Person', $items[0]['client_name']);
        $this->assertSame('linkedin', $items[0]['source']);
    }

    /** @test */
    public function locale_defaults_to_id_when_cf_country_is_id(): void
    {
        $response = $this->withHeaders(['CF-IPCountry' => 'ID'])
            ->getJson('/api/homepage/stats');

        $response->assertOk();
        $this->assertSame('id', App::getLocale());
        $response->assertCookie('lang_preference', 'id', false);
    }

    /** @test */
    public function locale_defaults_to_en_when_cf_country_is_us(): void
    {
        $response = $this->withHeaders(['CF-IPCountry' => 'US'])
            ->getJson('/api/homepage/stats');

        $response->assertOk();
        $this->assertSame('en', App::getLocale());
        $response->assertCookie('lang_preference', 'en', false);
    }

    /** @test */
    public function existing_cookie_wins_over_cf_country_header(): void
    {
        // - disableCookieEncryption(): API routes don't run EncryptCookies
        //   middleware, so the raw value must land in $request->cookie() as-is.
        // - withCredentials(): getJson() drops cookies unless this is set
        //   (Laravel default mimics fetch() w/o credentials).
        $response = $this->disableCookieEncryption()
            ->withCredentials()
            ->withCookies(['lang_preference' => 'en'])
            ->withHeaders(['CF-IPCountry' => 'ID'])
            ->getJson('/api/homepage/stats');

        $response->assertOk();
        $this->assertSame('en', App::getLocale());
    }

    /** @test */
    public function ip_api_fallback_fails_open_to_en_when_provider_disabled(): void
    {
        config(['services.geoip.fallback' => 'disabled']);

        // No CF-IPCountry header, no cookie → fallback consulted, returns null,
        // locale defaults to 'en'.
        $response = $this->getJson('/api/homepage/stats');

        $response->assertOk();
        $this->assertSame('en', App::getLocale());
        $response->assertCookie('lang_preference', 'en', false);
    }
}
