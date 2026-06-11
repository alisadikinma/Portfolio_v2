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
