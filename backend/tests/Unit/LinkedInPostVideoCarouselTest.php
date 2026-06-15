<?php

namespace Tests\Unit;

use App\Models\LinkedInPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — the `video_carousel` format anchor primitive: a LinkedInPost that
 * represents an IG-video repurpose carousel in the LinkedIn calendar but NEVER
 * publishes to LinkedIn. Two pieces: an identity check + a query scope that every
 * LinkedIn publisher uses to exclude these rows.
 */
class LinkedInPostVideoCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_video_carousel_and_scope_exclude(): void
    {
        $video = LinkedInPost::factory()->create(['format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL]);
        $text = LinkedInPost::factory()->create(['format' => 'text']);
        $carousel = LinkedInPost::factory()->create(['format' => 'carousel']);

        $this->assertTrue($video->isVideoCarousel());
        $this->assertFalse($text->isVideoCarousel());
        $this->assertFalse($carousel->isVideoCarousel());

        $kept = LinkedInPost::query()->excludeVideoCarousel()->pluck('id')->all();
        $this->assertContains($text->id, $kept);
        $this->assertContains($carousel->id, $kept);
        $this->assertNotContains($video->id, $kept);
    }
}
