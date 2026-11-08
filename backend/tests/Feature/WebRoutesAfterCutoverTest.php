<?php

namespace Tests\Feature;

use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PF-2 phase 5C: public pages are served by the Astro site (web/). Laravel's
 * web routes keep only /r/{code} (short links) and /login.
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
            'projects/{slug}', '{lang}/projects/{slug}', 'about', 'contact', 'test-route'] as $uri) {
            $this->assertNotContains($uri, $uris, "route still registered: {$uri}");
        }
    }

    public function test_short_link_route_keeps_the_web_group_for_ai_crawler_logging(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'r/{code}');

        $this->assertNotNull($route, '/r/{code} route missing');
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_only_login_and_short_links_remain_on_web(): void
    {
        $web = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('web', $r->gatherMiddleware(), true))
            ->filter(fn ($r) => ! str_starts_with($r->uri(), 'admin') && ! str_starts_with($r->uri(), 'livewire') && ! str_starts_with($r->uri(), 'filament'))
            ->map(fn ($r) => $r->uri())
            ->values()->all();

        $this->assertEqualsCanonicalizing(['login', 'r/{code}'], array_values(array_diff($web, ['up', 'sanctum/csrf-cookie', 'storage/{path}'])), 'unexpected web routes: '.implode(', ', $web));
    }
}
