<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PF-2 Phase 4G: sitemaps list the new site's pages, language-prefixed only,
 * each with id + en + x-default hreflang alternates.
 */
class SitemapNewPagesTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://alisadikinma.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'app.frontend_url' => self::SITE]);
        url()->forceRootUrl('http://localhost');
    }

    /** @return string[] */
    private function locs(string $xml): array
    {
        preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#', $xml, $m);

        return $m[1];
    }

    private function xml(string $path): string
    {
        $res = $this->get($path);
        $res->assertStatus(200);

        return $res->getContent();
    }

    private function allUrlXml(): string
    {
        return $this->xml('/api/sitemap.xml').$this->xml('/api/sitemap-posts.xml').$this->xml('/api/sitemap-projects.xml');
    }

    private function seedData(): array
    {
        $cat = Category::factory()->create(['name' => 'AI Agents', 'slug' => 'ai-agents']);
        $both = Post::factory()->create([
            'category_id' => $cat->id, 'slug' => 'both-langs', 'title' => 'Both', 'content' => 'x',
            'published' => true, 'published_at' => now()->subDays(3),
        ]);
        $both->translations()->create(['language' => 'en', 'title' => 'Both', 'slug' => 'both-langs', 'content' => 'x']);
        $both->translations()->create(['language' => 'id', 'title' => 'Keduanya', 'slug' => 'keduanya', 'content' => 'x']);
        $idOnly = Post::factory()->create([
            'category_id' => $cat->id, 'slug' => 'hanya-id', 'title' => 'Hanya', 'content' => 'x',
            'published' => true, 'published_at' => now()->subDays(2),
        ]);
        $idOnly->translations()->create(['language' => 'id', 'title' => 'Hanya ID', 'slug' => 'hanya-id', 'content' => 'x']);
        $draft = Post::factory()->create([
            'category_id' => $cat->id, 'slug' => 'draft-post', 'title' => 'Draft', 'content' => 'x', 'published' => false,
        ]);
        $draft->translations()->create(['language' => 'en', 'title' => 'Draft', 'slug' => 'draft-post', 'content' => 'x']);

        $award = Award::create(['title' => 'Global AI Demo Day 2026', 'description' => 'd', 'organization' => 'o', 'received_at' => '17 Jan 2026']);
        $noSlug = Award::create(['title' => 'No Slug Award', 'description' => 'd', 'organization' => 'o', 'received_at' => '2019']);
        DB::table('awards')->where('id', $noSlug->id)->update(['slug' => null]);

        Project::create(['title' => 'Visual Inspection', 'slug' => 'visual-inspection', 'category' => 'AI', 'description' => 'd', 'published' => true, 'is_active' => true]);
        Project::create(['title' => 'Hidden', 'slug' => 'hidden-project', 'category' => 'AI', 'description' => 'd', 'published' => true, 'is_active' => false]);

        return [$award];
    }

    public function test_pages_sitemap_lists_new_pages_with_hreflang(): void
    {
        [$award] = $this->seedData();
        $xml = $this->xml('/api/sitemap.xml');
        $locs = $this->locs($xml);

        foreach (['id', 'en'] as $l) {
            foreach (['', '/about', '/faq', '/contact', '/work', '/blog', '/projects'] as $p) {
                $this->assertContains(self::SITE."/$l$p", $locs, "missing /$l$p");
            }
            $this->assertContains(self::SITE."/$l/blog/category/ai-agents", $locs);
        }
        foreach (['/id/pelatihan-ai', '/en/ai-training', '/id/pembicara-ai', '/en/ai-speaker'] as $p) {
            $this->assertContains(self::SITE.$p, $locs, "missing $p");
        }
        $this->assertContains(self::SITE."/id/penghargaan/{$award->slug}", $locs);
        $this->assertContains(self::SITE."/en/awards/{$award->slug}", $locs);

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="id" href="'.self::SITE.'/id/pelatihan-ai"/>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="en" href="'.self::SITE.'/en/ai-training"/>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="x-default" href="'.self::SITE.'/en/ai-training"/>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="id" href="'.self::SITE.'/id/pembicara-ai"/>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="en" href="'.self::SITE."/en/awards/{$award->slug}\"/>", $xml);
    }

    public function test_every_url_entry_has_both_alternates_except_single_language_posts(): void
    {
        $this->seedData();
        $xml = $this->xml('/api/sitemap.xml');
        preg_match_all('#<url>(.*?)</url>#s', $xml, $blocks);
        $this->assertNotEmpty($blocks[1]);
        foreach ($blocks[1] as $block) {
            $this->assertStringContainsString('hreflang="id"', $block);
            $this->assertStringContainsString('hreflang="en"', $block);
            $this->assertStringContainsString('hreflang="x-default"', $block);
        }
    }

    public function test_no_unprefixed_public_url_in_any_sitemap(): void
    {
        $this->seedData();
        foreach ($this->locs($this->allUrlXml()) as $loc) {
            $this->assertMatchesRegularExpression('#^https://alisadikinma\.com/(id|en)(/|$)#', $loc, "unprefixed: $loc");
        }
    }

    public function test_awards_without_slug_are_absent(): void
    {
        $this->seedData();
        $xml = $this->xml('/api/sitemap.xml');
        $this->assertStringNotContainsString('no-slug-award', $xml);
        $this->assertDoesNotMatchRegularExpression('#/(awards|penghargaan)/</loc>#', $xml);
    }

    public function test_posts_only_in_languages_they_exist_in(): void
    {
        $this->seedData();
        $xml = $this->xml('/api/sitemap-posts.xml');
        $locs = $this->locs($xml);

        $this->assertContains(self::SITE.'/en/blog/both-langs', $locs);
        $this->assertContains(self::SITE.'/id/blog/keduanya', $locs);
        $this->assertContains(self::SITE.'/id/blog/hanya-id', $locs);
        $this->assertNotContains(self::SITE.'/en/blog/hanya-id', $locs);
        $this->assertStringNotContainsString('draft-post', $xml);
        // id-only post: its only alternate is id (no en href).
        $this->assertStringNotContainsString('/en/blog/hanya-id', $xml);
        $this->assertStringContainsString('hreflang="x-default" href="'.self::SITE.'/id/blog/hanya-id"', $xml);
    }

    public function test_posts_lastmod_present_and_never_faked_when_unknown(): void
    {
        $this->seedData();
        $xml = $this->xml('/api/sitemap-posts.xml');
        $this->assertMatchesRegularExpression('#<lastmod>\d{4}-\d{2}-\d{2}T#', $xml);
        $this->xml('/api/sitemap.xml');
        // Static pages carry no invented lastmod.
        $pages = $this->xml('/api/sitemap.xml');
        preg_match('#<url>\s*<loc>'.preg_quote(self::SITE, '#').'/id/about</loc>.*?</url>#s', $pages, $m);
        $this->assertNotEmpty($m);
        $this->assertStringNotContainsString('<lastmod>', $m[0]);
    }

    public function test_projects_sitemap_lists_active_projects_both_languages(): void
    {
        $this->seedData();
        $xml = $this->xml('/api/sitemap-projects.xml');
        $locs = $this->locs($xml);
        $this->assertContains(self::SITE.'/id/projects/visual-inspection', $locs);
        $this->assertContains(self::SITE.'/en/projects/visual-inspection', $locs);
        $this->assertStringNotContainsString('hidden-project', $xml);
        $this->assertStringContainsString('hreflang="id" href="'.self::SITE.'/id/projects/visual-inspection"', $xml);
    }

    public function test_sitemap_index_lists_the_three_sitemaps(): void
    {
        $locs = $this->locs($this->xml('/api/sitemap-index.xml'));
        $this->assertCount(3, $locs);
        $joined = implode(' ', $locs);
        foreach (['sitemap.xml', 'sitemap-posts.xml', 'sitemap-projects.xml'] as $f) {
            $this->assertStringContainsString($f, $joined);
        }
    }
}
