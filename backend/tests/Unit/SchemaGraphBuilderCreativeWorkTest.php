<?php

namespace Tests\Unit;

use App\Services\Seo\SchemaGraphBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for SchemaGraphBuilder::creativeWork() — the project-detail
 * JSON-LD builder mirroring blogPosting()'s omit-empty-keys style.
 */
class SchemaGraphBuilderCreativeWorkTest extends TestCase
{
    private SchemaGraphBuilder $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = new SchemaGraphBuilder();
    }

    public function test_creative_work_sets_type_and_required_identity_nodes(): void
    {
        $node = $this->schema->creativeWork([
            'name' => 'AI Visual Inspection Platform',
            'url' => 'https://alisadikinma.com/projects/ai-visual-inspection',
        ]);

        $this->assertSame('https://schema.org', $node['@context']);
        $this->assertSame('CreativeWork', $node['@type']);
        $this->assertSame('AI Visual Inspection Platform', $node['name']);

        // mainEntityOfPage carries the page URL.
        $this->assertSame('WebPage', $node['mainEntityOfPage']['@type']);
        $this->assertSame(
            'https://alisadikinma.com/projects/ai-visual-inspection',
            $node['mainEntityOfPage']['@id']
        );

        // Author is the canonical Person stub.
        $this->assertSame('Person', $node['author']['@type']);
        $this->assertSame('Ali Sadikin Ma', $node['author']['name']);
        $this->assertSame('https://alisadikinma.com', $node['author']['url']);

        // Publisher is the Organization.
        $this->assertSame('Organization', $node['publisher']['@type']);
        $this->assertSame('INDUSIA.ai', $node['publisher']['name']);
    }

    public function test_creative_work_includes_optional_keys_when_present(): void
    {
        $node = $this->schema->creativeWork([
            'name' => 'Project X',
            'url' => 'https://alisadikinma.com/projects/x',
            'description' => 'A real case study.',
            'image' => 'https://alisadikinma.com/storage/x.jpg',
            'dateCreated' => '2025-01-01T00:00:00+00:00',
            'dateModified' => '2025-06-01T00:00:00+00:00',
            'inLanguage' => 'en',
            'keywords' => 'AI, Computer Vision',
            'about' => 'Manufacturing',
        ]);

        $this->assertSame('https://alisadikinma.com/projects/x', $node['url']);
        $this->assertSame('A real case study.', $node['description']);
        $this->assertSame('https://alisadikinma.com/storage/x.jpg', $node['image']);
        $this->assertSame('2025-01-01T00:00:00+00:00', $node['dateCreated']);
        $this->assertSame('2025-06-01T00:00:00+00:00', $node['dateModified']);
        $this->assertSame('en', $node['inLanguage']);
        $this->assertSame('AI, Computer Vision', $node['keywords']);
        $this->assertSame('Manufacturing', $node['about']);
    }

    public function test_creative_work_omits_empty_optional_keys(): void
    {
        $node = $this->schema->creativeWork([
            'name' => 'Minimal Project',
            'url' => 'https://alisadikinma.com/projects/minimal',
            'description' => '',
            'image' => null,
            'dateCreated' => null,
            'keywords' => '',
            'about' => null,
        ]);

        // Empty/null optionals are dropped entirely.
        $this->assertArrayNotHasKey('description', $node);
        $this->assertArrayNotHasKey('image', $node);
        $this->assertArrayNotHasKey('dateCreated', $node);
        $this->assertArrayNotHasKey('dateModified', $node);
        $this->assertArrayNotHasKey('inLanguage', $node);
        $this->assertArrayNotHasKey('keywords', $node);
        $this->assertArrayNotHasKey('about', $node);

        // Required identity nodes always survive.
        $this->assertArrayHasKey('author', $node);
        $this->assertArrayHasKey('publisher', $node);
        $this->assertSame('CreativeWork', $node['@type']);
    }
}
