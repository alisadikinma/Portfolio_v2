<?php

namespace App\Services\Seo;

/**
 * Server-side enricher for the built Vue SPA shell (frontend/dist/index.html).
 *
 * Crawlers and LLM/GEO bots that don't execute JavaScript only ever see the
 * raw shell, so per-page <title>/meta/OG, a JSON-LD graph, hreflang alternates
 * and (for content pages) a crawlable body must be spliced in server-side. Vue
 * still mounts into #app on top, so real users are unaffected.
 *
 * This class is intentionally pure + DB-free: it takes the shell HTML string
 * plus a normalized $seo array and returns enriched HTML. All data resolution
 * (models, translations, schema) lives in the controller/builders that call it.
 *
 * $seo shape (every key optional — missing keys are no-ops):
 *   [
 *     'title'       => string,
 *     'description' => string,
 *     'keywords'    => string,
 *     'canonical'   => string,
 *     'robots'      => string,                       // e.g. 'index, follow'
 *     'og'          => ['type'=>, 'url'=>, 'title'=>, 'description'=>, 'image'=>, 'locale'=>],
 *     'twitter'     => ['url'=>, 'title'=>, 'description'=>, 'image'=>],
 *     'hreflang'    => ['en'=>url, 'id'=>url, 'x-default'=>url],
 *     'jsonLd'      => [ [..assoc..], [..assoc..] ], // each emitted as one <script>
 *     'bodyHtml'    => ?string,                      // trusted server-rendered markup for #app
 *   ]
 */
class SeoHtmlComposer
{
    public function compose(string $shellHtml, array $seo): string
    {
        $html = $shellHtml;

        $html = $this->replaceHeadTags($html, $seo);
        $html = $this->insertBeforeHeadClose($html, $seo);
        $html = $this->injectBody($html, $seo['bodyHtml'] ?? null);

        return $html;
    }

    /**
     * Replace the existing scalar head tags in-place. Uses preg_replace_callback
     * with a literal closure return so replacement values containing `$` or `\`
     * (e.g. a title "Cost is $5") are NEVER interpreted as backreferences — the
     * latent bug in the original routes/web.php $injectOg implementation.
     */
    private function replaceHeadTags(string $html, array $seo): string
    {
        if (isset($seo['title'])) {
            $t = $this->esc($seo['title']);
            $html = $this->replaceFirst($html, '/<title>[^<]*<\/title>/i', '<title>' . $t . '</title>');
            $html = $this->replaceFirst($html, '/<meta\s+name="title"\s+content="[^"]*"\s*\/?>/i', '<meta name="title" content="' . $t . '">');
        }

        if (isset($seo['description'])) {
            $d = $this->esc($seo['description']);
            $html = $this->replaceFirst($html, '/<meta\s+name="description"\s+content="[^"]*"\s*\/?>/i', '<meta name="description" content="' . $d . '">');
        }

        if (isset($seo['keywords'])) {
            $k = $this->esc($seo['keywords']);
            $html = $this->replaceFirst($html, '/<meta\s+name="keywords"\s+content="[^"]*"\s*\/?>/i', '<meta name="keywords" content="' . $k . '">');
        }

        if (isset($seo['robots'])) {
            $r = $this->esc($seo['robots']);
            $html = $this->replaceFirst($html, '/<meta\s+name="robots"\s+content="[^"]*"\s*\/?>/i', '<meta name="robots" content="' . $r . '">');
        }

        if (isset($seo['canonical'])) {
            $c = $this->esc($seo['canonical']);
            $html = $this->replaceFirst($html, '/<link\s+rel="canonical"\s+href="[^"]*"\s*\/?>/i', '<link rel="canonical" href="' . $c . '">');
        }

        foreach (($seo['og'] ?? []) as $key => $value) {
            $v = $this->esc((string) $value);
            $keyEsc = preg_quote($key, '/');
            $html = $this->replaceFirst(
                $html,
                '/<meta\s+property="og:' . $keyEsc . '"\s+content="[^"]*"\s*\/?>/i',
                '<meta property="og:' . $key . '" content="' . $v . '">'
            );
        }

        foreach (($seo['twitter'] ?? []) as $key => $value) {
            $v = $this->esc((string) $value);
            $keyEsc = preg_quote($key, '/');
            $html = $this->replaceFirst(
                $html,
                '/<meta\s+name="twitter:' . $keyEsc . '"\s+content="[^"]*"\s*\/?>/i',
                '<meta name="twitter:' . $key . '" content="' . $v . '">'
            );
        }

        return $html;
    }

    /**
     * Splice hreflang <link>s + each JSON-LD block immediately before </head>.
     * Uses a substring splice (not preg_replace) so JSON containing `$`/`\`
     * never collides with replacement-string backreference syntax.
     */
    private function insertBeforeHeadClose(string $html, array $seo): string
    {
        $insert = '';

        foreach (($seo['hreflang'] ?? []) as $lang => $url) {
            $insert .= '    <link rel="alternate" hreflang="' . $this->esc((string) $lang)
                . '" href="' . $this->esc((string) $url) . '">' . "\n";
        }

        foreach (($seo['jsonLd'] ?? []) as $block) {
            if (empty($block)) {
                continue;
            }
            $json = json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                continue;
            }
            $insert .= '    <script type="application/ld+json">' . $json . '</script>' . "\n";
        }

        if ($insert === '') {
            return $html;
        }

        $pos = stripos($html, '</head>');
        if ($pos === false) {
            return $html;
        }

        return substr($html, 0, $pos) . $insert . substr($html, $pos);
    }

    /**
     * Replace the empty <div id="app"></div> with the same div carrying
     * pre-mount crawlable markup. Vue's app.mount('#app') overwrites it on
     * hydration, so real users see the SPA and crawlers see real content.
     */
    private function injectBody(string $html, ?string $bodyHtml): string
    {
        if ($bodyHtml === null || $bodyHtml === '') {
            return $html;
        }

        return preg_replace_callback(
            '/<div id="app">\s*<\/div>/i',
            fn () => '<div id="app">' . $bodyHtml . '</div>',
            $html,
            1
        ) ?? $html;
    }

    private function replaceFirst(string $html, string $pattern, string $replacement): string
    {
        $result = preg_replace_callback($pattern, fn () => $replacement, $html, 1);

        return $result ?? $html;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
