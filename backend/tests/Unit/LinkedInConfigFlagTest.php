<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * PHPUnit class style (NOT Pest) — Pest is not installed in this repo, and a
 * top-level test()/expect() file crashes the whole PHPUnit suite build with
 * "Call to undefined function test()", reddening CI for every run regardless
 * of the --filter. Keep new tests in PHPUnit class style.
 */
class LinkedInConfigFlagTest extends TestCase
{
    public function test_repurpose_source_mirror_regenerate_defaults_to_true(): void
    {
        $this->assertTrue(config('linkedin.repurpose_source_mirror_regenerate'));
    }

    public function test_auto_pipeline_repurpose_source_mirror_default_is_false(): void
    {
        $this->assertFalse(config('linkedin.repurpose_source_mirror'));
    }
}
