<?php

namespace Tests\Unit;

use App\Services\Seo\SeoHtmlComposer;
use Tests\TestCase;

/**
 * Pure, DB-free unit tests for the SSR head + body splicer.
 *
 * The composer takes the built SPA shell (frontend/dist/index.html) as a
 * string plus a normalized $seo array, and returns enriched HTML. No Laravel
 * services, no DB — every input is passed in, so these run fast on CI.
 */
class SeoHtmlComposerTest extends TestCase
{
    private function shell(): string
    {
        // Minimal faithful copy of the real dist/index.html anchors
        // (tags use `>` not `/>`, matching production build output).
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Ali Sadikin Ma - AI Generalist Expert</title>
    <meta name="title" content="Ali Sadikin Ma - AI Generalist Expert">
    <meta name="description" content="Default description.">
    <meta name="keywords" content="default, keywords">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://alisadikinma.com/">
    <meta property="og:title" content="Default OG Title">
    <meta property="og:description" content="Default OG description.">
    <meta property="og:image" content="https://alisadikinma.com/og-image.jpg">
    <meta property="og:locale" content="en_US">
    <meta name="twitter:url" content="https://alisadikinma.com/">
    <meta name="twitter:title" content="Default TW Title">
    <meta name="twitter:description" content="Default TW description.">
    <meta name="twitter:image" content="https://alisadikinma.com/og-image.jpg">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://alisadikinma.com/">
    <script type="application/ld+json" data-schema="person">{"@type":"Person"}</script>
  </head>
  <body>
    <div id="app"></div>
  </body>
</html>
HTML;
    }

    private function composer(): SeoHtmlComposer
    {
        return new SeoHtmlComposer();
    }

    public function test_replaces_title_description_canonical(): void
    {
        $html = $this->composer()->compose($this->shell(), [
            'title' => 'My Post Title',
            'description' => 'My post description.',
            'canonical' => 'https://alisadikinma.com/en/blog/my-post',
        ]);

        $this->assertStringContainsString('<title>My Post Title</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="My post description.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://alisadikinma.com/en/blog/my-post">', $html);
        // Untouched defaults must NOT linger for the fields we set.
        $this->assertStringNotContainsString('Default description.', $html);
    }

    public function test_replaces_og_and_twitter_tags(): void
    {
        $html = $this->composer()->compose($this->shell(), [
            'og' => [
                'type' => 'article',
                'url' => 'https://alisadikinma.com/en/blog/x',
                'title' => 'OG Post',
                'description' => 'OG desc',
                'image' => 'https://alisadikinma.com/storage/x.jpg',
                'locale' => 'id_ID',
            ],
            'twitter' => [
                'url' => 'https://alisadikinma.com/en/blog/x',
                'title' => 'TW Post',
                'description' => 'TW desc',
                'image' => 'https://alisadikinma.com/storage/x.jpg',
            ],
        ]);

        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="OG Post">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="id_ID">', $html);
        $this->assertStringContainsString('<meta name="twitter:title" content="TW Post">', $html);
    }

    public function test_inserts_jsonld_and_hreflang_before_head_close(): void
    {
        $html = $this->composer()->compose($this->shell(), [
            'hreflang' => [
                'en' => 'https://alisadikinma.com/en/blog/x',
                'id' => 'https://alisadikinma.com/id/blog/x',
                'x-default' => 'https://alisadikinma.com/blog/x',
            ],
            'jsonLd' => [
                ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => 'X'],
                ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList'],
            ],
        ]);

        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="https://alisadikinma.com/en/blog/x">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="https://alisadikinma.com/blog/x">', $html);
        $this->assertStringContainsString('"@type":"BlogPosting"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);

        // Inserted content must appear BEFORE </head>, and the pre-existing
        // static person schema must be preserved (not clobbered).
        $headClose = strpos($html, '</head>');
        $this->assertNotFalse($headClose);
        $this->assertLessThan($headClose, strpos($html, '"BlogPosting"'));
        $this->assertStringContainsString('data-schema="person"', $html);
    }

    public function test_injects_body_into_app_div(): void
    {
        $html = $this->composer()->compose($this->shell(), [
            'bodyHtml' => '<article><h1>Hello</h1><p>Real content.</p></article>',
        ]);

        $this->assertStringContainsString('<div id="app"><article><h1>Hello</h1><p>Real content.</p></article></div>', $html);
    }

    public function test_returns_shell_unchanged_when_anchors_missing(): void
    {
        $bare = '<html><head></head><body><main>no anchors</main></body></html>';
        $out = $this->composer()->compose($bare, [
            'title' => 'Whatever',
            'bodyHtml' => '<article>x</article>',
            'jsonLd' => [['@type' => 'WebSite']],
        ]);

        // No <title> or #app to replace → those splices are no-ops, but
        // jsonLd still inserts before </head>.
        $this->assertStringNotContainsString('<article>x</article>', $out); // no #app anchor
        $this->assertStringContainsString('"@type":"WebSite"', $out);
        $this->assertStringContainsString('</head>', $out);
    }

    public function test_escapes_html_and_encodes_jsonld_safely(): void
    {
        $html = $this->composer()->compose($this->shell(), [
            'title' => 'Cost is $5 & "quoted" <b>',
            'jsonLd' => [
                ['@type' => 'Article', 'headline' => 'Slash/in/url & émojis ✓'],
            ],
        ]);

        // Title escaped for HTML attribute/text context.
        $this->assertStringContainsString('Cost is $5 &amp; &quot;quoted&quot; &lt;b&gt;', $html);
        // JSON-LD: slashes + unicode NOT escaped (clean for crawlers).
        $this->assertStringContainsString('Slash/in/url & émojis ✓', $html);
        $this->assertStringNotContainsString('Slash\/in\/url', $html);
    }
}
