<?php

namespace Tests\Feature;

use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PF-2 phase 5C: public pages are served by the Astro site (web/). Laravel's
 * web routes keep only /r/{code} (short links), /login and /test-route.
 */
class WebRoutesAfterCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_link_still_redirects(): void
    {
        ShortLink::create([
            'code' => 'cut0ver',
            'target_url' => 'https://alisadikinma.com/blog/x?utm_source=linkedin',
            'post_id' => null,
            'source_platform' => 'linkedin',
        ]);

        $this->get('/r/cut0ver')
            ->assertStatus(301)
            ->assertRedirect('https://alisadikinma.com/blog/x?utm_source=linkedin');
    }

    public function test_prerender_routes_are_gone_from_route_list(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->map(fn ($r) => $r->uri())
            ->all();

        foreach (['/', 'blog', 'blog/{slug}', 'blog/category/{slug}', '{lang}/blog',
            '{lang}/blog/{slug}', '{lang}/blog/category/{slug}', 'faq', 'projects',
            'projects/{slug}', '{lang}/projects/{slug}', 'about', 'contact'] as $uri) {
            $this->assertNotContains($uri, $uris, "route still registered: {$uri}");
        }
    }

    public function test_no_route_uses_a_prerender_controller(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertStringNotContainsString('Prerender', $route->getActionName());
        }
    }
}
