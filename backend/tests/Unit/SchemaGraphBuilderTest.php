<?php

namespace Tests\Unit;

use App\Services\Seo\SchemaGraphBuilder;
use Tests\TestCase;

/**
 * Pure, DB-free unit tests for JSON-LD builders. Every method takes plain
 * arrays (no Eloquent) so these run fast on CI without a database.
 */
class SchemaGraphBuilderTest extends TestCase
{
    private function builder(): SchemaGraphBuilder
    {
        return new SchemaGraphBuilder();
    }

    public function test_blogPosting_has_required_schema_fields(): void
    {
        $schema = $this->builder()->blogPosting([
            'headline' => 'My Post',
            'description' => 'Desc',
            'url' => 'https://alisadikinma.com/en/blog/my-post',
            'image' => 'https://alisadikinma.com/storage/x.jpg',
            'datePublished' => '2026-06-01T00:00:00+07:00',
            'dateModified' => '2026-06-05T00:00:00+07:00',
            'authorName' => 'Ali Sadikin Ma',
            'inLanguage' => 'en',
            'keywords' => 'AI, agents',
        ]);

        $this->assertSame('BlogPosting', $schema['@type']);
        $this->assertSame('My Post', $schema['headline']);
        $this->assertSame('Person', $schema['author']['@type']);
        $this->assertSame('Ali Sadikin Ma', $schema['author']['name']);
        $this->assertSame('2026-06-01T00:00:00+07:00', $schema['datePublished']);
        $this->assertSame('2026-06-05T00:00:00+07:00', $schema['dateModified']);
        $this->assertSame('https://alisadikinma.com/storage/x.jpg', $schema['image']);
        $this->assertSame('https://alisadikinma.com/en/blog/my-post', $schema['mainEntityOfPage']['@id']);
        $this->assertSame('Organization', $schema['publisher']['@type']);
        $this->assertSame('en', $schema['inLanguage']);
    }

    public function test_faqPage_maps_faq_schema_array(): void
    {
        $schema = $this->builder()->faqPage([
            ['question' => 'Q1', 'answer' => 'A1'],
            ['question' => 'Q2', 'answer' => 'A2'],
        ]);

        $this->assertSame('FAQPage', $schema['@type']);
        $this->assertCount(2, $schema['mainEntity']);
        $this->assertSame('Question', $schema['mainEntity'][0]['@type']);
        $this->assertSame('Q1', $schema['mainEntity'][0]['name']);
        $this->assertSame('A1', $schema['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_faqPage_accepts_name_text_shape(): void
    {
        // Stored faq_schema may already use schema.org-ish keys.
        $schema = $this->builder()->faqPage([
            ['name' => 'Q', 'acceptedAnswer' => ['text' => 'A']],
        ]);
        $this->assertSame('Q', $schema['mainEntity'][0]['name']);
        $this->assertSame('A', $schema['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_faqPage_returns_null_when_empty(): void
    {
        $this->assertNull($this->builder()->faqPage([]));
        $this->assertNull($this->builder()->faqPage(null));
    }

    public function test_breadcrumbList_positions_increment(): void
    {
        $schema = $this->builder()->breadcrumbList([
            ['name' => 'Home', 'url' => 'https://alisadikinma.com/'],
            ['name' => 'Blog', 'url' => 'https://alisadikinma.com/blog'],
            ['name' => 'Post', 'url' => 'https://alisadikinma.com/blog/x'],
        ]);

        $this->assertSame('BreadcrumbList', $schema['@type']);
        $this->assertSame(1, $schema['itemListElement'][0]['position']);
        $this->assertSame(3, $schema['itemListElement'][2]['position']);
        $this->assertSame('Home', $schema['itemListElement'][0]['name']);
        $this->assertSame('https://alisadikinma.com/blog/x', $schema['itemListElement'][2]['item']);
    }

    public function test_itemList_from_posts(): void
    {
        $schema = $this->builder()->itemList([
            ['name' => 'P1', 'url' => 'https://alisadikinma.com/blog/p1'],
            ['name' => 'P2', 'url' => 'https://alisadikinma.com/blog/p2'],
        ]);

        $this->assertSame('ItemList', $schema['@type']);
        $this->assertSame(1, $schema['itemListElement'][0]['position']);
        $this->assertSame('https://alisadikinma.com/blog/p1', $schema['itemListElement'][0]['url']);
        $this->assertSame('P1', $schema['itemListElement'][0]['name']);
    }

    public function test_collectionPage_wraps_itemlist(): void
    {
        $schema = $this->builder()->collectionPage(
            ['name' => 'AI Agents', 'url' => 'https://alisadikinma.com/blog/category/ai'],
            [['name' => 'P1', 'url' => 'https://alisadikinma.com/blog/p1']]
        );

        $this->assertSame('CollectionPage', $schema['@type']);
        $this->assertSame('AI Agents', $schema['name']);
        $this->assertSame('ItemList', $schema['mainEntity']['@type']);
        $this->assertSame('P1', $schema['mainEntity']['itemListElement'][0]['name']);
    }

    public function test_webSite_has_publisher(): void
    {
        $schema = $this->builder()->webSite();
        $this->assertSame('WebSite', $schema['@type']);
        $this->assertSame('https://alisadikinma.com', $schema['url']);
        $this->assertArrayHasKey('publisher', $schema);
    }

    public function test_person_matches_static_index_html(): void
    {
        $schema = $this->builder()->person();
        $this->assertSame('Person', $schema['@type']);
        $this->assertSame('Ali Sadikin Ma', $schema['name']);
        $this->assertSame('https://alisadikinma.com', $schema['url']);
        $this->assertContains('https://www.linkedin.com/in/alisadikinma/', $schema['sameAs']);
        $this->assertContains('https://github.com/alisadikinma', $schema['sameAs']);
    }

    public function test_organization_is_indusia(): void
    {
        $schema = $this->builder()->organization();
        $this->assertSame('Organization', $schema['@type']);
        $this->assertSame('INDUSIA.ai', $schema['name']);
    }
}
