<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Social Studio Phase B — derived `title` + cover hint on the repurpose list.
 *
 * The Social Studio card needs a human topic title (the operator "gak tau
 * topiknya") + a cover hint. compact() gains:
 *   - title:      rewritten.title → first non-empty line of extracted.caption
 *                 (≤120 chars) → null
 *   - slide_count: count of captured slide-*.jpg in the private slides dir
 *   - has_cover:   slide_count > 0
 * show() also surfaces `title` for the detail header.
 */
class RepurposeJobListTitleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function listRow(int $id): array
    {
        $res = $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/repurpose');
        $res->assertOk();
        foreach ($res->json('data') as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        $this->fail("job {$id} not in list");
    }

    public function test_compact_title_from_rewritten(): void
    {
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'rewritten' => ['title' => '5 Cara AI Ubah Marketing', 'body' => '...'],
            'extracted' => ['caption' => 'ignored when rewritten present'],
        ]);

        $this->assertSame('5 Cara AI Ubah Marketing', $this->listRow($job->id)['title']);
    }

    public function test_compact_title_falls_back_to_caption_first_line(): void
    {
        $job = RepurposeJob::factory()->create([
            'status' => 'extracted',
            'rewritten' => null,
            'extracted' => ['caption' => "  Marketing pakai AI itu gini  \nbaris kedua diabaikan"],
        ]);

        $this->assertSame('Marketing pakai AI itu gini', $this->listRow($job->id)['title']);
    }

    public function test_compact_title_null_when_neither(): void
    {
        $job = RepurposeJob::factory()->create([
            'status' => 'received',
            'rewritten' => null,
            'extracted' => null,
        ]);

        $this->assertNull($this->listRow($job->id)['title']);
    }

    public function test_compact_includes_slide_count_and_has_cover(): void
    {
        Storage::fake('local');
        $job = RepurposeJob::factory()->create(['status' => 'captured', 'slides_path' => 'repurpose/7']);
        Storage::disk('local')->put('repurpose/7/slide-01.jpg', 'A');
        Storage::disk('local')->put('repurpose/7/slide-02.jpg', 'B');

        $row = $this->listRow($job->id);
        $this->assertSame(2, $row['slide_count']);
        $this->assertTrue($row['has_cover']);
    }

    public function test_compact_has_cover_false_when_no_slides(): void
    {
        Storage::fake('local');
        $job = RepurposeJob::factory()->create(['status' => 'received']);

        $row = $this->listRow($job->id);
        $this->assertSame(0, $row['slide_count']);
        $this->assertFalse($row['has_cover']);
    }

    public function test_compact_cover_url_falls_back_to_generated_carousel_when_source_purged(): void
    {
        Storage::fake('local');
        // Post requires category_id (NOT NULL on sqlite) → make one + a carousel draft.
        $cat = \App\Models\Category::create(['name' => 'AI & Tech']);
        $post = \App\Models\Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $li = \App\Models\LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'carousel_slides' => [
                ['slide_number' => 1, 'image_url' => 'https://alisadikinma.com/storage/linkedin-carousel/x-slide-01.png', 'image_status' => 'done'],
                ['slide_number' => 2, 'image_url' => 'https://alisadikinma.com/storage/linkedin-carousel/x-slide-02.png', 'image_status' => 'done'],
            ],
        ]);
        // Drafted carousel job whose private source slides have been purged.
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'mode' => 'carousel',
            'slides_path' => 'repurpose/99',
            'linkedin_post_id' => $li->id,
        ]);

        $row = $this->listRow($job->id);
        $this->assertFalse($row['has_cover']);
        $this->assertSame('https://alisadikinma.com/storage/linkedin-carousel/x-slide-01.png', $row['cover_url']);
    }

    public function test_compact_cover_url_null_when_no_linked_carousel(): void
    {
        Storage::fake('local');
        $job = RepurposeJob::factory()->create(['status' => 'received', 'linkedin_post_id' => null]);

        $this->assertNull($this->listRow($job->id)['cover_url']);
    }

    public function test_compact_render_state_reflects_in_flight_carousel(): void
    {
        Storage::fake('local');
        $cat = \App\Models\Category::create(['name' => 'AI & Tech']);
        $post = \App\Models\Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $li = \App\Models\LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'carousel_slides' => [
                ['slide_number' => 1, 'image_status' => 'generating', 'image_url' => null],
                ['slide_number' => 2, 'image_status' => 'pending', 'image_url' => null],
            ],
        ]);
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'mode' => 'carousel',
            'linkedin_post_id' => $li->id,
        ]);

        $this->assertSame('generating', $this->listRow($job->id)['render_state']);
    }

    public function test_compact_render_state_ready_when_all_slides_done(): void
    {
        Storage::fake('local');
        $cat = \App\Models\Category::create(['name' => 'AI & Tech']);
        $post = \App\Models\Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $li = \App\Models\LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'carousel_slides' => [
                ['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://x/1.png'],
                ['slide_number' => 2, 'image_status' => 'done', 'image_url' => 'https://x/2.png'],
            ],
        ]);
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'mode' => 'carousel',
            'linkedin_post_id' => $li->id,
        ]);

        $this->assertSame('ready', $this->listRow($job->id)['render_state']);
    }

    public function test_compact_render_state_null_for_blog_mode(): void
    {
        $job = RepurposeJob::factory()->create(['status' => 'drafted', 'mode' => 'blog', 'linkedin_post_id' => null]);

        $this->assertNull($this->listRow($job->id)['render_state']);
    }

    public function test_show_includes_title(): void
    {
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'rewritten' => ['title' => 'Judul Detail'],
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/repurpose/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul Detail');
    }
}
