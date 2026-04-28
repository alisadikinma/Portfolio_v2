<?php

namespace Tests\Feature;

use Tests\TestCase;

class CarouselGenConfigTest extends TestCase
{
    /** @test */
    public function it_returns_ssh_as_default_driver(): void
    {
        $this->assertSame('ssh', config('carousel-gen.driver'));
    }

    /** @test */
    public function it_returns_600_as_default_timeout_seconds_int(): void
    {
        // 600s mirrors LINKEDIN_GEN_TIMEOUT_SECONDS (production-validated on
        // post #24 wall=369s). See config/carousel-gen.php for full rationale.
        $value = config('carousel-gen.timeout_seconds');

        $this->assertSame(600, $value);
    }

    /** @test */
    public function it_resolves_refs_pipeline_as_non_empty_string(): void
    {
        $value = config('carousel-gen.refs_pipeline');

        $this->assertIsString($value);
        $this->assertNotEmpty($value);
    }
}
