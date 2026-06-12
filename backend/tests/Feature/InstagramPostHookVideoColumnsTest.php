<?php

namespace Tests\Feature;

use App\Models\InstagramPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase A — GROK hook video schema. instagram_posts gains 4 columns tracking
 * the per-IG-draft hook-slide video (GROK image-to-video): url, status,
 * job_uuid (poll key), error. All nullable so IG siblings created before the
 * feature shipped stay valid (treated as all-image carousels). Mirrors the
 * carousel-slide image_status pattern.
 *
 * FK-free by design — assert schema + mass-assignment without a DB insert, to
 * sidestep the documented sqlite posts.* FK noise in this env.
 */
class InstagramPostHookVideoColumnsTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = [
        'hook_video_url',
        'hook_video_status',
        'hook_video_job_uuid',
        'hook_video_error',
    ];

    public function test_migration_adds_hook_video_columns(): void
    {
        foreach (self::COLUMNS as $col) {
            $this->assertTrue(
                Schema::hasColumn('instagram_posts', $col),
                "instagram_posts is missing column {$col}"
            );
        }
    }

    public function test_hook_video_columns_are_mass_assignable(): void
    {
        $model = (new InstagramPost)->fill([
            'hook_video_url' => 'https://alisadikinma.com/storage/linkedin-carousel/grok-hook-1.mp4',
            'hook_video_status' => 'generating',
            'hook_video_job_uuid' => 'a8b16062-662a-11f1-9021-9abb3defa979',
            'hook_video_error' => null,
        ]);

        $attrs = $model->getAttributes();
        $this->assertSame('generating', $attrs['hook_video_status']);
        $this->assertSame('a8b16062-662a-11f1-9021-9abb3defa979', $attrs['hook_video_job_uuid']);
        $this->assertArrayHasKey('hook_video_url', $attrs);
        $this->assertArrayHasKey('hook_video_error', $attrs);
    }
}
