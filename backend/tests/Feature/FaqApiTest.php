<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature coverage for the public GET /api/faq endpoint (Phase 3, GEO Pillar 2):
 * App\Http\Controllers\Api\FaqController. Returns the curated Q&A from
 * config/faq.php in the project's standard {success, data} shape — the same
 * single source the SSR /faq page renders. No DB, no auth.
 */
class FaqApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Default APP_URL contains the XAMPP subpath which breaks getJson()
        // routing under sqlite — same workaround the other Feature tests use.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
    }

    public function test_faq_endpoint_returns_curated_items(): void
    {
        $items = config('faq.items', []);
        $this->assertGreaterThanOrEqual(8, count($items), 'config/faq.php must curate 8-12 Q&A.');

        $res = $this->getJson('/api/faq');

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonCount(count($items), 'data');
        $res->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['question', 'answer'],
            ],
        ]);

        // Non-empty answer-first copy, full name present (GEO requirement).
        $res->assertJsonPath('data.0.question', $items[0]['question']);
        $first = $res->json('data.0');
        $this->assertNotEmpty($first['answer']);
    }

    public function test_faq_endpoint_is_public_no_auth(): void
    {
        // No Sanctum actingAs — must still 200.
        $this->getJson('/api/faq')->assertStatus(200);
    }
}
