<?php

namespace Tests\Unit;

use App\Services\CvMasterMarkdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit coverage for the CV Master Markdown rendering service.
 *
 * The service composes a single dense markdown document from settings,
 * projects, awards, and posts. Tests focus on deterministic rendering
 * behavior; full integration is exercised by CvMasterMarkdownApiTest.
 */
class CvMasterMarkdownServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function render_returns_non_empty_string_with_h1_header(): void
    {
        $service = app(CvMasterMarkdownService::class);

        $body = $service->render();

        $this->assertNotEmpty($body);
        $this->assertStringStartsWith('# ', $body);
    }

    /** @test */
    public function skill_domains_config_has_at_least_three_domains(): void
    {
        $domains = config('cv.skill_domains');

        $this->assertIsArray($domains);
        $this->assertGreaterThanOrEqual(3, count($domains));
        foreach ($domains as $domain) {
            $this->assertArrayHasKey('key', $domain);
            $this->assertArrayHasKey('label', $domain);
            $this->assertArrayHasKey('years', $domain);
            $this->assertArrayHasKey('bullets', $domain);
            $this->assertIsArray($domain['bullets']);
            $this->assertNotEmpty($domain['bullets']);
        }
    }
}
