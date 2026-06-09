<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Home SSR output must carry one Organization JSON-LD node with aggregateRating
 * + review[] computed from active testimonials, and the home cache must bust on
 * any testimonial change. Uses the fixture-shell pattern from SpaPrerenderTest.
 */
class SpaPrerenderHomeRatingTest extends TestCase
{
    use RefreshDatabase;

    private string $shellPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://alisadikinma.com']);

        $this->shellPath = sys_get_temp_dir() . '/seo-shell-' . uniqid() . '.html';
        file_put_contents($this->shellPath, <<<'HTML'
<!doctype html>
<html lang="en"><head>
  <title>Ali Sadikin Ma</title>
  <meta name="description" content="default">
</head><body><div id="app"></div></body></html>
HTML);
        config(['seo.spa_shell_path' => $this->shellPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->shellPath);
        parent::tearDown();
    }

    private function makeTestimonial(array $overrides = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'client_name' => 'Jane Doe',
            'company_name' => 'Acme Mfg',
            'job_title' => 'VP Ops',
            'testimonial_text' => '<p>Cut our AOI defects fast — better and cheaper.</p>',
            'star_rating' => 5,
            'is_active' => true,
            'sort_order' => 0,
            'source' => 'linkedin',
        ], $overrides));
    }

    public function test_home_injects_organization_aggregate_rating_from_testimonials(): void
    {
        $this->makeTestimonial(['client_name' => 'A', 'star_rating' => 5, 'sort_order' => 0]);
        $this->makeTestimonial(['client_name' => 'B', 'star_rating' => 4, 'sort_order' => 1]);
        $this->makeTestimonial(['client_name' => 'C', 'star_rating' => 5, 'sort_order' => 2]);

        $res = $this->get('/');

        $res->assertStatus(200);
        // Organization node present with @id.
        $res->assertSee('"@type":"Organization"', false);
        $res->assertSee('alisadikinma.com/#organization', false);
        // aggregateRating: avg(5,4,5)=4.666 → 4.7, reviewCount 3.
        $res->assertSee('"@type":"AggregateRating"', false);
        $res->assertSee('"ratingValue":"4.7"', false);
        $res->assertSee('"reviewCount":3', false);
        // review[] built from testimonials.
        $res->assertSee('"@type":"Review"', false);
    }

    public function test_home_emits_plain_organization_when_no_active_testimonials(): void
    {
        $this->makeTestimonial(['is_active' => false]);

        $res = $this->get('/');

        $res->assertStatus(200);
        $res->assertSee('"@type":"Organization"', false);
        // No fabricated rating when there are no active testimonials.
        $res->assertDontSee('"@type":"AggregateRating"', false);
    }

    public function test_testimonial_save_purges_home_cache(): void
    {
        $t = $this->makeTestimonial();

        // Prime the home cache.
        $this->get('/')->assertStatus(200);
        $this->assertTrue(Cache::has('seo_html:home'));

        // A testimonial change must bust the home SSR cache.
        $t->update(['star_rating' => 3]);
        $this->assertFalse(Cache::has('seo_html:home'));
    }
}
