<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature coverage for the dedicated /faq SSR-enrichment surface (Phase 3,
 * GEO Pillar 2): SpaPrerenderController::faq + routes/web.php. Mirrors
 * ProjectDetailSsrTest — points the controller at a fixture SPA shell via
 * config('seo.spa_shell_path') so it never depends on a real frontend build,
 * and pins config('app.url') for deterministic URLs. FAQ content is sourced
 * from config/faq.php (no DB), so no RefreshDatabase is needed.
 */
class FaqSsrTest extends TestCase
{
    private string $shellPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://alisadikinma.com']);

        $this->shellPath = sys_get_temp_dir() . '/seo-shell-' . uniqid() . '.html';
        file_put_contents($this->shellPath, $this->fixtureShell());
        config(['seo.spa_shell_path' => $this->shellPath]);

        // Defensive: never read a primed entry from a prior run/test.
        Cache::forget('seo_html:faq');
    }

    protected function tearDown(): void
    {
        @unlink($this->shellPath);
        Cache::forget('seo_html:faq');
        parent::tearDown();
    }

    private function fixtureShell(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <title>Ali Sadikin Ma</title>
  <meta name="title" content="Ali Sadikin Ma">
  <meta name="description" content="default">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://alisadikinma.com/">
  <meta property="og:title" content="Ali Sadikin Ma">
  <meta property="og:description" content="default">
  <meta name="twitter:url" content="https://alisadikinma.com/">
  <meta name="twitter:title" content="Ali Sadikin Ma">
  <meta name="twitter:description" content="default">
  <meta name="robots" content="noindex">
  <link rel="canonical" href="https://alisadikinma.com/">
</head>
<body>
  <div id="app"></div>
  <script type="module" src="/assets/app.js"></script>
</body>
</html>
HTML;
    }

    public function test_faq_injects_crawlable_dl_and_faqpage_jsonld(): void
    {
        $res = $this->get('/faq');

        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        // Crawlable <dl> body spliced into #app.
        $res->assertSee('<dl>', false);
        // At least one real curated question text from config/faq.php appears.
        $firstQuestion = config('faq.items.0.question');
        $this->assertNotEmpty($firstQuestion);
        $res->assertSee($firstQuestion, false);
        // App div no longer empty (Vue hydrates over it).
        $res->assertDontSee('<div id="app"></div>', false);

        // FAQPage JSON-LD with Question/Answer mainEntity.
        $res->assertSee('"@type":"FAQPage"', false);
        $res->assertSee('"@type":"Question"', false);
        $res->assertSee('"@type":"Answer"', false);

        // Per-page head: canonical points at /faq, robots indexable.
        $res->assertSee('<link rel="canonical" href="https://alisadikinma.com/faq">', false);
        $res->assertSee('content="index, follow"', false);
    }

    public function test_faq_jsonld_mainentity_questions_match_config_count(): void
    {
        $items = config('faq.items', []);
        $this->assertGreaterThanOrEqual(8, count($items), 'config/faq.php must curate 8-12 Q&A.');

        $html = $this->get('/faq')->getContent();

        // Extract the FAQPage JSON-LD block and assert its mainEntity is a list
        // of Question nodes, one per curated item — proves the single source
        // (config/faq.php) drives the schema, not a hardcoded copy.
        $this->assertMatchesRegularExpression('/<script type="application\/ld\+json">(.*?"@type":"FAQPage".*?)<\/script>/s', $html);
        preg_match('/<script type="application\/ld\+json">(\{.*?"FAQPage".*?\})<\/script>/s', $html, $m);
        $this->assertNotEmpty($m[1] ?? null, 'FAQPage JSON-LD block not found.');

        $decoded = json_decode($m[1], true);
        $this->assertIsArray($decoded);
        $this->assertSame('FAQPage', $decoded['@type']);
        $this->assertCount(count($items), $decoded['mainEntity']);
        $this->assertSame('Question', $decoded['mainEntity'][0]['@type']);
        $this->assertSame('Answer', $decoded['mainEntity'][0]['acceptedAnswer']['@type']);
    }

    public function test_faq_html_is_cached(): void
    {
        $this->assertFalse(Cache::has('seo_html:faq'));

        $this->get('/faq')->assertStatus(200);

        $this->assertTrue(Cache::has('seo_html:faq'));
    }
}
