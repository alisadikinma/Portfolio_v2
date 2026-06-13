<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\VideoChromeRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A (#4 chrome bug) — VideoChromeRenderer::handle() must ignore the
 * placeholder brand slug `creator-brand` (and fall back to @alisadikinma)
 * instead of rendering a literal `@creator-brand` in the footer.
 *
 * handle() is a protected test seam; exposed here via an anonymous subclass.
 * See docs/plans/2026-06-13-video-rebrand-quality-pass.md Phase A.
 */
class VideoChromeRendererTest extends TestCase
{
    use RefreshDatabase;

    private function resolveHandle(): string
    {
        $renderer = new class extends VideoChromeRenderer
        {
            public function publicHandle(): string
            {
                return $this->handle();
            }
        };

        return $renderer->publicHandle();
    }

    public function test_handle_ignores_placeholder_slug(): void
    {
        // The seeded placeholder slug, no linkedin handle configured.
        Setting::create(['group' => 'creator_brand', 'key' => 'creator_brand_slug', 'value' => 'creator-brand']);

        $this->assertSame('@alisadikinma', $this->resolveHandle());
    }

    public function test_handle_ignores_underscore_placeholder_and_empty(): void
    {
        Setting::create(['group' => 'creator_brand', 'key' => 'creator_brand_slug', 'value' => 'creator_brand']);
        $this->assertSame('@alisadikinma', $this->resolveHandle());
    }

    public function test_handle_prefers_linkedin_creator_handle(): void
    {
        Setting::create(['group' => 'linkedin', 'key' => 'creator_handle', 'value' => 'alisadikinma']);
        Setting::create(['group' => 'creator_brand', 'key' => 'creator_brand_slug', 'value' => 'creator-brand']);

        $this->assertSame('@alisadikinma', $this->resolveHandle());
    }

    public function test_handle_uses_real_slug_when_not_placeholder(): void
    {
        Setting::create(['group' => 'creator_brand', 'key' => 'creator_brand_slug', 'value' => 'someoneelse']);

        $this->assertSame('@someoneelse', $this->resolveHandle());
    }

    public function test_handle_normalizes_linkedin_handle_without_at(): void
    {
        Setting::create(['group' => 'linkedin', 'key' => 'creator_handle', 'value' => '@customhandle']);

        $this->assertSame('@customhandle', $this->resolveHandle());
    }
}
