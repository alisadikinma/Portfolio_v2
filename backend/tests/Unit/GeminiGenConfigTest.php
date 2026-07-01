<?php

namespace Tests\Unit;

use Tests\TestCase;

class GeminiGenConfigTest extends TestCase
{
    public function test_base_url_defaults_to_snapgen(): void
    {
        $this->assertSame('https://api.snapgen.ai/uapi/v1', config('geminigen.base_url'));
    }

    public function test_rollout_flags_default_off(): void
    {
        $this->assertFalse(config('geminigen.use_indusia_images'));
        $this->assertFalse(config('geminigen.use_indusia_video'));
    }

    public function test_client_driver_and_paths_present(): void
    {
        $this->assertSame('local', config('geminigen.client_driver'));
        $this->assertNotEmpty(config('geminigen.client_path'));
        $this->assertNotEmpty(config('geminigen.client_repo'));
    }

    public function test_no_service_hardcodes_the_dead_geminigen_domain(): void
    {
        $files = [
            app_path('Services/ImageGenerationService.php'),
            app_path('Services/LinkedInCarouselImageService.php'),
            app_path('Services/GeminiGenVideoService.php'),
            app_path('Console/Commands/ProcessPendingImages.php'),
            app_path('Console/Commands/PollHookVideos.php'),
            app_path('Console/Commands/PollRebrandAssets.php'),
        ];

        foreach ($files as $file) {
            $this->assertStringNotContainsString(
                'api.geminigen.ai',
                file_get_contents($file),
                "Dead pre-rebrand domain still hardcoded in {$file}"
            );
        }
    }
}
