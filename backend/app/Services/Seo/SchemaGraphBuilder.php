<?php

namespace App\Services\Seo;

/**
 * Pure JSON-LD builders for the SSR-enrichment layer.
 *
 * Every method takes plain arrays (never Eloquent) and returns an associative
 * array ready to be json_encode()'d by SeoHtmlComposer. The Person/Organization
 * identity is hardcoded to match the static blocks already baked into
 * frontend/index.html so the entity graph stays consistent across every page
 * (GEO levers G2/G3 — see CLAUDE.md). Site URL is the canonical production
 * identity, not env-dependent.
 */
class SchemaGraphBuilder
{
    private const SITE_URL = 'https://alisadikinma.com';
    private const PERSON_NAME = 'Ali Sadikin Ma';
    private const ORG_NAME = 'INDUSIA.ai';
    private const ORG_URL = 'https://indusia.ai';

    private const SAME_AS = [
        'https://www.linkedin.com/in/alisadikinma/',
        'https://www.instagram.com/alisadikinma',
        'https://www.tiktok.com/@alisadikinma',
        'https://www.youtube.com/@alisadikinma',
        'https://github.com/alisadikinma',
    ];

    /**
     * Canonical Person entity — mirrors the static data-schema="person" block
     * in frontend/index.html. Single source so the two never drift.
     */
    public function person(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => self::PERSON_NAME,
            'alternateName' => 'Ali Sadikin',
            'url' => self::SITE_URL,
            'jobTitle' => 'AI Generalist',
            'description' => 'Ali Sadikin Ma is an AI Generalist who turns frontier AI models into shipped products — not slide decks. 17 years building across 16 countries; ranked #1 at the Global AI Demo Day 2026. Now teaching AI Agents, Vibe Coding, and Generative Video.',
            'knowsAbout' => [
                'AI Agents', 'AI Agent Operating Systems', 'Vibe Coding',
                'Generative AI Video', 'AI Automation', 'Computer Vision',
                'Edge AI', 'Large Language Model Applications',
            ],
            'knowsLanguage' => ['Indonesian', 'English', 'Mandarin'],
            'nationality' => ['@type' => 'Country', 'name' => 'Indonesia'],
            'worksFor' => $this->organization(),
            'sameAs' => self::SAME_AS,
        ];
    }

    public function organization(): array
    {
        return [
            '@type' => 'Organization',
            'name' => self::ORG_NAME,
            'url' => self::ORG_URL,
        ];
    }

    /**
     * Standalone Organization entity carrying aggregateRating + review[] for the
     * homepage graph (GEO review signal — reviews lift both classic search and
     * LLM citation). Values are computed by the caller from real testimonials and
     * passed in; this builder NEVER fabricates a rating.
     *
     * @param array $rating  ['ratingValue' => float, 'reviewCount' => int]
     * @param array $reviews list of ['author' => string, 'body' => string(HTML), 'rating' => int]
     *
     * When reviewCount < 1 the aggregateRating + review[] keys are omitted
     * entirely — a real Organization node is still returned (with @id) but with
     * no zero/fake rating that crawlers could mistrust.
     */
    public function organizationWithRating(array $rating, array $reviews = []): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => self::SITE_URL . '/#organization',
            'name' => self::ORG_NAME,
            'url' => self::ORG_URL,
            'sameAs' => self::SAME_AS,
        ];

        $reviewCount = (int) ($rating['reviewCount'] ?? 0);
        if ($reviewCount < 1) {
            return $node;
        }

        $node['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) round((float) ($rating['ratingValue'] ?? 0), 1),
            'reviewCount' => $reviewCount,
            'bestRating' => '5',
            'worstRating' => '1',
        ];

        $items = [];
        foreach ($reviews as $review) {
            $author = trim((string) ($review['author'] ?? ''));
            $body = $this->plainText((string) ($review['body'] ?? ''), 280);
            if ($author === '' || $body === '') {
                continue;
            }
            $items[] = [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $author],
                'reviewBody' => $body,
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (string) ((int) ($review['rating'] ?? 5)),
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
            ];
        }
        if (!empty($items)) {
            $node['review'] = $items;
        }

        return $node;
    }

    /** Strip HTML, collapse whitespace, and cap at $limit chars (testimonial_text is HTML). */
    private function plainText(string $html, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit)) . '…';
    }

    /**
     * WebSite entity with the Person as publisher. Carries a SearchAction so
     * search engines can surface a sitelinks searchbox.
     */
    public function webSite(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => self::PERSON_NAME,
            'url' => self::SITE_URL,
            'inLanguage' => ['en', 'id'],
            'publisher' => [
                '@type' => 'Person',
                'name' => self::PERSON_NAME,
                'url' => self::SITE_URL,
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => self::SITE_URL . '/blog?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * BlogPosting / Article for a blog detail page.
     *
     * @param array $data headline, description, url, image, datePublished,
     *                    dateModified, authorName, inLanguage, keywords
     */
    public function blogPosting(array $data): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $data['headline'] ?? '',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $data['url'] ?? self::SITE_URL,
            ],
            'author' => [
                '@type' => 'Person',
                'name' => $data['authorName'] ?? self::PERSON_NAME,
                'url' => self::SITE_URL,
            ],
            'publisher' => $this->organization(),
        ];

        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }
        if (!empty($data['image'])) {
            $schema['image'] = $data['image'];
        }
        if (!empty($data['datePublished'])) {
            $schema['datePublished'] = $data['datePublished'];
        }
        if (!empty($data['dateModified'])) {
            $schema['dateModified'] = $data['dateModified'];
        }
        if (!empty($data['inLanguage'])) {
            $schema['inLanguage'] = $data['inLanguage'];
        }
        if (!empty($data['keywords'])) {
            $schema['keywords'] = $data['keywords'];
        }

        return $schema;
    }

    /**
     * CreativeWork for a project detail page. Mirrors blogPosting()'s
     * omit-empty-keys style; @type is CreativeWork (a portfolio case study, not
     * an article). Author is the canonical Person stub, publisher the
     * Organization — both consistent with the rest of the entity graph.
     *
     * @param array $data name, description, url, image, dateCreated,
     *                    dateModified, inLanguage, keywords, about
     */
    public function creativeWork(array $data): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $data['name'] ?? '',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $data['url'] ?? self::SITE_URL,
            ],
            'author' => [
                '@type' => 'Person',
                'name' => self::PERSON_NAME,
                'url' => self::SITE_URL,
            ],
            'publisher' => $this->organization(),
        ];

        if (!empty($data['url'])) {
            $schema['url'] = $data['url'];
        }
        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }
        if (!empty($data['image'])) {
            $schema['image'] = $data['image'];
        }
        if (!empty($data['dateCreated'])) {
            $schema['dateCreated'] = $data['dateCreated'];
        }
        if (!empty($data['dateModified'])) {
            $schema['dateModified'] = $data['dateModified'];
        }
        if (!empty($data['inLanguage'])) {
            $schema['inLanguage'] = $data['inLanguage'];
        }
        if (!empty($data['keywords'])) {
            $schema['keywords'] = $data['keywords'];
        }
        if (!empty($data['about'])) {
            $schema['about'] = $data['about'];
        }

        return $schema;
    }

    /**
     * @param array $items list of ['name' => string, 'url' => string]
     */
    public function breadcrumbList(array $items): array
    {
        $elements = [];
        $position = 1;
        foreach ($items as $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $item['name'] ?? '',
                'item' => $item['url'] ?? '',
            ];
            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * FAQPage from a stored faq_schema array. Tolerates two shapes:
     *   ['question' => , 'answer' => ]   (plugin-authored)
     *   ['name' => , 'acceptedAnswer' => ['text' => ]]   (schema.org-ish)
     * Returns null when there is nothing to render (no empty FAQPage emitted).
     */
    public function faqPage(?array $faq): ?array
    {
        if (empty($faq)) {
            return null;
        }

        // Already a full FAQPage graph → pass through.
        if (isset($faq['@type']) && $faq['@type'] === 'FAQPage') {
            return $faq + ['@context' => 'https://schema.org'];
        }

        $mainEntity = [];
        foreach ($faq as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $question = $entry['question'] ?? $entry['name'] ?? null;
            $answer = $entry['answer']
                ?? ($entry['acceptedAnswer']['text'] ?? null);
            if ($question === null || $answer === null) {
                continue;
            }
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if (empty($mainEntity)) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * @param array $posts list of ['name' => string, 'url' => string]
     */
    public function itemList(array $posts): array
    {
        $elements = [];
        $position = 1;
        foreach ($posts as $post) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'url' => $post['url'] ?? '',
                'name' => $post['name'] ?? '',
            ];
            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * CollectionPage (blog list / category) wrapping an ItemList of posts.
     *
     * @param array $page  ['name' => string, 'url' => string]
     * @param array $posts list of ['name' => string, 'url' => string]
     */
    public function collectionPage(array $page, array $posts): array
    {
        $list = $this->itemList($posts);
        unset($list['@context']); // nested — context lives on the parent

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $page['name'] ?? '',
            'url' => $page['url'] ?? self::SITE_URL,
            'mainEntity' => $list,
        ];
    }
}
