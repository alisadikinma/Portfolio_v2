<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Gallery;
use App\Models\Project;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicVisibilityFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function mkService(bool $active, string $slug): Service
    {
        return Service::create(['title' => $slug, 'slug' => $slug, 'description' => 'd', 'content' => 'c', 'is_active' => $active]);
    }

    private function mkTestimonial(bool $active, string $name): Testimonial
    {
        return Testimonial::create(['client_name' => $name, 'testimonial_text' => 't', 'is_active' => $active]);
    }

    private function mkAward(bool $active, string $title): Award
    {
        $award = new Award(['title' => $title, 'description' => 'd', 'organization' => 'o', 'received_at' => '2025']);
        $award->forceFill(['is_active' => $active])->save();

        return $award;
    }

    private function mkProject(bool $published, bool $active, string $slug): Project
    {
        return Project::create(['title' => $slug, 'slug' => $slug, 'description' => 'd', 'category' => 'ai', 'published' => $published, 'is_active' => $active]);
    }

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);
    }

    // ---- Services
    public function test_service_show_hidden_from_guest_visible_to_admin(): void
    {
        $this->mkService(false, 'hidden-svc');
        $this->getJson('/api/services/hidden-svc')->assertStatus(404);
        $this->admin();
        $this->getJson('/api/services/hidden-svc')->assertStatus(200);
    }

    public function test_service_index_ignores_guest_active_filter(): void
    {
        $this->mkService(false, 'hidden-svc');
        $this->mkService(true, 'live-svc');
        $r = $this->getJson('/api/services?active=0&all=1&per_page=50');
        $slugs = collect($r->json('data'))->pluck('slug')->all();
        $this->assertSame(['live-svc'], $slugs);
        $this->admin();
        $slugs = collect($this->getJson('/api/services?all=1&per_page=50')->json('data'))->pluck('slug')->sort()->values()->all();
        $this->assertSame(['hidden-svc', 'live-svc'], $slugs);
    }

    // ---- Testimonials
    public function test_testimonial_show_hidden_from_guest_visible_to_admin(): void
    {
        $t = $this->mkTestimonial(false, 'Hidden');
        $this->getJson("/api/testimonials/{$t->id}")->assertStatus(404);
        $this->admin();
        $this->getJson("/api/testimonials/{$t->id}")->assertStatus(200);
        $this->getJson('/api/admin/testimonials?is_active=0')->assertStatus(200);
    }

    public function test_testimonial_show_active_is_public(): void
    {
        $t = $this->mkTestimonial(true, 'Live');
        $this->getJson("/api/testimonials/{$t->id}")->assertStatus(200);
    }

    // ---- Awards
    public function test_award_show_and_index_hide_inactive_from_guest(): void
    {
        $hidden = $this->mkAward(false, 'Hidden');
        $live = $this->mkAward(true, 'Live');
        $this->getJson("/api/awards/{$hidden->id}")->assertStatus(404);
        $this->getJson("/api/awards/{$hidden->id}/galleries")->assertStatus(404);
        $this->getJson("/api/awards/{$live->id}")->assertStatus(200);
        $ids = collect($this->getJson('/api/awards')->json('data'))->pluck('id')->all();
        $this->assertSame([$live->id], $ids);

        $this->admin();
        $this->getJson("/api/admin/awards/{$hidden->id}")->assertStatus(200);
        $this->getJson("/api/awards/{$hidden->id}")->assertStatus(200);
    }

    public function test_award_galleries_hide_inactive_gallery_from_guest(): void
    {
        $award = $this->mkAward(true, 'A');
        Gallery::factory()->create(['award_id' => $award->id, 'is_active' => false, 'title' => 'hid']);
        Gallery::factory()->create(['award_id' => $award->id, 'is_active' => true, 'title' => 'vis']);
        $titles = collect($this->getJson("/api/awards/{$award->id}/galleries")->json('data.galleries'))->pluck('title')->all();
        $this->assertSame(['vis'], $titles);
    }

    // ---- Galleries
    public function test_gallery_show_hidden_from_guest_visible_to_admin(): void
    {
        $g = Gallery::factory()->create(['is_active' => false]);
        $this->getJson("/api/galleries/{$g->id}")->assertStatus(404);
        $this->getJson("/api/gallery/{$g->id}")->assertStatus(404);
        $this->admin();
        $this->getJson("/api/galleries/{$g->id}")->assertStatus(200);
        $this->getJson("/api/admin/galleries/{$g->id}")->assertStatus(200);
    }

    public function test_gallery_index_ignores_guest_is_active_filter(): void
    {
        $hidden = Gallery::factory()->create(['is_active' => false]);
        $live = Gallery::factory()->create(['is_active' => true]);
        $ids = collect($this->getJson('/api/galleries?is_active=0')->json('data'))->pluck('id')->all();
        $this->assertSame([$live->id], $ids);
        $this->admin();
        $ids = collect($this->getJson('/api/admin/galleries?is_active=0')->json('data'))->pluck('id')->all();
        $this->assertSame([$hidden->id], $ids);
        $ids = collect($this->getJson('/api/admin/galleries')->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$hidden->id, $live->id], $ids);
    }

    // ---- Projects
    public function test_project_index_hides_unpublished_and_inactive_from_guest(): void
    {
        $this->mkProject(false, true, 'draft-p');
        $this->mkProject(true, false, 'off-p');
        $this->mkProject(true, true, 'live-p');
        $slugs = collect($this->getJson('/api/projects')->json('data'))->pluck('slug')->all();
        $this->assertSame(['live-p'], $slugs);
        $slugs = collect($this->getJson('/api/projects?limit=10')->json('data'))->pluck('slug')->all();
        $this->assertSame(['live-p'], $slugs);

        $this->admin();
        $slugs = collect($this->getJson('/api/projects')->json('data'))->pluck('slug')->sort()->values()->all();
        $this->assertSame(['draft-p', 'live-p', 'off-p'], $slugs);
        $this->assertCount(3, $this->getJson('/api/admin/projects')->json('data'));
    }

    // ---- Gallery items of inactive gallery
    public function test_inactive_gallery_items_hidden_from_guest_visible_to_admin(): void
    {
        $g = Gallery::factory()->create(['is_active' => false]);
        $this->getJson("/api/galleries/{$g->id}/items")->assertStatus(404);
        $this->getJson("/api/gallery/{$g->id}/items")->assertStatus(404);
        $this->admin();
        $this->getJson("/api/galleries/{$g->id}/items")->assertStatus(200);
    }

    public function test_active_gallery_items_are_public(): void
    {
        $g = Gallery::factory()->create(['is_active' => true]);
        $this->getJson("/api/galleries/{$g->id}/items")->assertStatus(200);
    }

    // ---- Token scoping: only admin (wildcard) tokens see hidden records
    public function test_cv_read_token_does_not_see_inactive_records(): void
    {
        $this->mkService(false, 'hidden-svc');
        $g = Gallery::factory()->create(['is_active' => false]);
        $this->mkProject(false, true, 'draft-p');
        Sanctum::actingAs(User::factory()->create(), ['cv:read']);
        $this->getJson('/api/services/hidden-svc')->assertStatus(404);
        $this->getJson("/api/galleries/{$g->id}/items")->assertStatus(404);
        $this->getJson('/api/projects/draft-p')->assertStatus(404);
    }

    public function test_automation_token_does_not_see_inactive_records(): void
    {
        $this->mkService(false, 'hidden-svc');
        Sanctum::actingAs(User::factory()->create(), ['post:read', 'post:write']);
        $this->getJson('/api/services/hidden-svc')->assertStatus(404);
    }

    public function test_real_login_token_is_admin(): void
    {
        $this->mkService(false, 'hidden-svc');
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        $this->withToken($token)->getJson('/api/services/hidden-svc')->assertStatus(200);
    }

    public function test_cv_minted_token_does_not_see_inactive_records(): void
    {
        $this->mkService(false, 'hidden-svc');
        $token = User::factory()->create()->createToken('cv-x', ['cv:read'])->plainTextToken;
        $this->withToken($token)->getJson('/api/services/hidden-svc')->assertStatus(404);
    }

    // ---- Service ?active= admin filter
    public function test_service_admin_active_filter_parses_booleans(): void
    {
        $this->mkService(false, 'off-svc');
        $this->mkService(true, 'on-svc');
        $this->admin();
        $off = collect($this->getJson('/api/services?active=false&all=1')->json('data'))->pluck('slug')->all();
        $on = collect($this->getJson('/api/services?active=true&all=1')->json('data'))->pluck('slug')->all();
        $this->assertSame(['off-svc'], $off);
        $this->assertSame(['on-svc'], $on);
    }
}
