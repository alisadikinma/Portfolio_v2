<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase F — a scheduled video_carousel anchor shows on the LinkedIn-tab calendar
 * with its format + a repurpose_job_id, so the FE card can render a video badge and
 * deep-link to /admin/repurpose/{id} (where the Zernio publish/schedule UI lives).
 */
class VideoCarouselCalendarSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_row_exposes_format_and_repurpose_job_id_for_video(): void
    {
        $cat = Category::firstOrCreate(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Vid', 'content' => '<p>b</p>']);
        $when = now()->addDay()->startOfMinute();
        $anchor = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'status' => 'awaiting_publish',
            'scheduled_at' => $when,
            'cancel_window_ends_at' => $when,
        ]);
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'status' => 'drafted',
            'linkedin_post_id' => $anchor->id,
        ]);

        // A normal text draft in range must NOT carry a repurpose_job_id.
        $textPost = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Txt', 'content' => '<p>b</p>']);
        $text = LinkedInPost::factory()->awaitingPublish()->create([
            'post_id' => $textPost->id,
            'format' => 'text',
            'scheduled_at' => $when,
            'cancel_window_ends_at' => $when,
        ]);

        $res = $this->actingAs(User::factory()->create(), 'sanctum')->getJson(
            '/api/admin/linkedin-posts/calendar?from=' . now()->toDateString() . '&to=' . now()->addDays(3)->toDateString()
        );
        $res->assertOk();
        $items = collect($res->json('data.items'));

        $videoRow = $items->firstWhere('id', $anchor->id);
        $this->assertNotNull($videoRow, 'video_carousel anchor must appear on the calendar');
        $this->assertSame('video_carousel', $videoRow['format']);
        $this->assertSame($job->id, $videoRow['repurpose_job_id']);

        $textRow = $items->firstWhere('id', $text->id);
        $this->assertNotNull($textRow);
        $this->assertNull($textRow['repurpose_job_id'] ?? null);
    }
}
