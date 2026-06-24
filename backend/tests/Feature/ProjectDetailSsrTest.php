<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature coverage for project-detail SSR-enrichment (Phase 2):
 * SpaPrerenderController::projectDetail + routes/web.php. Mirrors
 * SpaPrerenderTest — points the controller at a fixture SPA shell via
 * config('seo.spa_shell_path') so it never depends on a real frontend build,
 * and pins config('app.url') for deterministic URLs.
 */
class ProjectDetailSsrTest extends TestCase
{
    use RefreshDatabase;

    private string $shellPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://alisadikinma.com']);

        $this->shellPath = sys_get_temp_dir() . '/seo-shell-' . uniqid() . '.html';
        file_put_contents($this->shellPath, $this->fixtureShell());
        config(['seo.spa_shell_path' => $this->shellPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->shellPath);
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
  <meta property="og:image" content="https://alisadikinma.com/og.jpg">
  <meta property="og:locale" content="en_US">
  <meta name="twitter:url" content="https://alisadikinma.com/">
  <meta name="twitter:title" content="Ali Sadikin Ma">
  <meta name="twitter:description" content="default">
  <meta name="twitter:image" content="https://alisadikinma.com/og.jpg">
  <link rel="canonical" href="https://alisadikinma.com/">
</head>
<body>
  <div id="app"></div>
  <script type="module" src="/assets/app.js"></script>
</body>
</html>
HTML;
    }

    private function makeProject(array $overrides = [], array $translation = []): Project
    {
        $project = Project::create(array_merge([
            'title' => 'AI Visual Inspection Platform',
            'slug' => 'ai-visual-inspection',
            'description' => 'Real-time PCB defect detection for EMS lines.',
            'content' => '<p>Deployed PatchCore + YOLO across three production lines.</p>',
            'image' => 'https://alisadikinma.com/storage/projects/hero.jpg',
            'og_image' => 'https://alisadikinma.com/storage/projects/hero.jpg',
            'client' => 'Sat Nusapersada',
            'role' => 'AI Lead',
            'domain' => 'Manufacturing',
            'category' => 'Computer Vision',
            'result' => 'Cut escape defects 60%.',
            'is_active' => true,
        ], $overrides));

        if (!empty($translation)) {
            $project->translations()->create(array_merge([
                'language' => 'en',
                'title' => 'AI Visual Inspection Platform',
                'slug' => 'ai-visual-inspection',
                'content' => '<p>Deployed PatchCore + YOLO across three production lines.</p>',
                'meta_title' => 'AI Visual Inspection Platform | Ali Sadikin Ma',
                'meta_description' => 'How we built real-time PCB defect detection.',
            ], $translation));
        }

        return $project->fresh('translations');
    }

    public function test_project_detail_injects_crawlable_article_body_and_jsonld(): void
    {
        $this->makeProject();

        $res = $this->get('/projects/ai-visual-inspection');

        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        // Crawlable case-study body spliced into #app.
        $res->assertSee('<article>', false);
        $res->assertSee('Deployed PatchCore', false);
        // Case-study meta surfaced as a <dl>.
        $res->assertSee('Sat Nusapersada', false);
        // Per-page head.
        $res->assertSee('<link rel="canonical" href="https://alisadikinma.com/projects/ai-visual-inspection">', false);
        // JSON-LD entity graph.
        $res->assertSee('"@type":"CreativeWork"', false);
        $res->assertSee('"@type":"BreadcrumbList"', false);
        // SPA app div no longer empty.
        $res->assertDontSee('<div id="app"></div>', false);
    }

    public function test_project_detail_uses_translation_meta_when_present(): void
    {
        $this->makeProject([], ['language' => 'en']);

        $res = $this->get('/projects/ai-visual-inspection');

        $res->assertStatus(200);
        $res->assertSee('<title>AI Visual Inspection Platform | Ali Sadikin Ma</title>', false);
    }

    public function test_project_detail_returns_404_for_inactive_project(): void
    {
        $this->makeProject(['is_active' => false]);

        $this->get('/projects/ai-visual-inspection')->assertStatus(404);
    }

    public function test_project_detail_returns_404_for_missing_slug(): void
    {
        $this->get('/projects/does-not-exist')->assertStatus(404);
    }

    public function test_lang_prefixed_project_detail_sets_hreflang(): void
    {
        $this->makeProject();

        $res = $this->get('/en/projects/ai-visual-inspection');

        $res->assertStatus(200);
        $res->assertSee('hreflang="id"', false);
        $res->assertSee('https://alisadikinma.com/id/projects/ai-visual-inspection', false);
        $res->assertSee('hreflang="x-default"', false);
    }

    public function test_html_is_cached_and_purged_on_project_update(): void
    {
        $project = $this->makeProject();

        // Prime the cache.
        $this->get('/projects/ai-visual-inspection')->assertStatus(200);
        $this->assertTrue(Cache::has('seo_html:project_detail::ai-visual-inspection'));

        // A project save must purge the entry (Project::boot saved hook).
        $project->update(['description' => 'Updated description.']);
        $this->assertFalse(Cache::has('seo_html:project_detail::ai-visual-inspection'));
    }

    public function test_slug_rename_purges_the_old_slug_detail_cache(): void
    {
        $project = $this->makeProject();

        $this->get('/projects/ai-visual-inspection')->assertStatus(200);
        $this->assertTrue(Cache::has('seo_html:project_detail::ai-visual-inspection'));

        // Renaming the slug must purge the OLD slug's cache (getOriginal).
        $project->update(['slug' => 'ai-visual-inspection-v2']);
        $this->assertFalse(Cache::has('seo_html:project_detail::ai-visual-inspection'));
    }
}
