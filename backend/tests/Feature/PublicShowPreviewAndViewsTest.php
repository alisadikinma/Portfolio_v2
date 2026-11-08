<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicShowPreviewAndViewsTest extends TestCase
{
    use RefreshDatabase;

    private function mkPost(bool $published, string $slug): Post
    {
        $category = Category::firstOrCreate(['slug' => 'ai'], ['name' => 'AI']);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => $slug,
            'content' => '<p>body</p>',
            'published' => $published,
            'published_at' => $published ? now()->subDay() : null,
        ]);
        $post->translations()->create(['language' => 'en', 'title' => 'T', 'slug' => $slug, 'content' => '<p>b</p>']);

        return $post;
    }

    public function test_guest_preview_of_draft_is_404(): void
    {
        $this->mkPost(false, 'draft-a');

        $this->getJson('/api/posts/draft-a?preview=1')->assertStatus(404);
        $this->getJson('/api/posts/draft-a')->assertStatus(404);
    }

    public function test_admin_preview_of_draft_is_200(): void
    {
        $this->mkPost(false, 'draft-b');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/posts/draft-b?preview=1')->assertStatus(200);
    }

    public function test_admin_without_preview_flag_still_404_on_draft(): void
    {
        $this->mkPost(false, 'draft-c');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/posts/draft-c')->assertStatus(404);
    }

    public function test_guest_preview_of_published_post_is_200(): void
    {
        $this->mkPost(true, 'live-a');

        $this->getJson('/api/posts/live-a?preview=1')->assertStatus(200);
    }

    public function test_view_counted_by_default(): void
    {
        $p = $this->mkPost(true, 'live-b');

        $this->getJson('/api/posts/live-b')->assertStatus(200);

        $this->assertSame(1, (int) $p->fresh()->views);
    }

    public function test_track_zero_skips_increment(): void
    {
        $p = $this->mkPost(true, 'live-c');

        $this->getJson('/api/posts/live-c?track=0')->assertStatus(200);

        $this->assertSame(0, (int) $p->fresh()->views);
    }

    public function test_ai_bot_user_agent_skips_increment(): void
    {
        $p = $this->mkPost(true, 'live-d');
        $ua = array_key_first(config('ai_bots.map'));

        $this->getJson('/api/posts/live-d', ['User-Agent' => "Mozilla/5.0 (compatible; {$ua}/1.0)"])->assertStatus(200);

        $this->assertSame(0, (int) $p->fresh()->views);
    }

    public function test_bot_match_is_case_insensitive(): void
    {
        $p = $this->mkPost(true, 'live-e');
        $ua = strtolower((string) array_key_first(config('ai_bots.map')));

        $this->getJson('/api/posts/live-e', ['User-Agent' => $ua])->assertStatus(200);

        $this->assertSame(0, (int) $p->fresh()->views);
    }

    public function test_guest_cannot_read_unpublished_project(): void
    {
        $project = Project::create(['title' => 'P', 'slug' => 'secret-proj', 'description' => 'd', 'category' => 'ai', 'published' => false, 'is_active' => true]);

        $this->getJson('/api/projects/secret-proj')->assertStatus(404);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/projects/secret-proj')->assertStatus(200);
    }

    public function test_guest_can_read_published_project(): void
    {
        Project::create(['title' => 'P', 'slug' => 'open-proj', 'description' => 'd', 'category' => 'ai', 'published' => true, 'is_active' => true]);

        $this->getJson('/api/projects/open-proj')->assertStatus(200);
    }
}
