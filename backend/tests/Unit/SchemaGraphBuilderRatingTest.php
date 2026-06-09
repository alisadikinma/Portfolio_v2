<?php

namespace Tests\Unit;

use App\Services\Seo\SchemaGraphBuilder;
use Tests\TestCase;

/**
 * Pure, DB-free unit tests for the Organization+aggregateRating builder used on
 * the homepage entity graph (GEO review signal — Neil Patel: reviews lift Google
 * +108% AND ChatGPT +256%). Every value must come from the passed data; empty
 * input must NEVER fabricate a rating.
 */
class SchemaGraphBuilderRatingTest extends TestCase
{
    private function builder(): SchemaGraphBuilder
    {
        return new SchemaGraphBuilder();
    }

    public function test_organizationWithRating_builds_aggregateRating_and_reviews(): void
    {
        $node = $this->builder()->organizationWithRating(
            ['ratingValue' => 4.7, 'reviewCount' => 3],
            [
                ['author' => 'Jane Doe', 'body' => '<p>Great <b>work</b> on our line.</p>', 'rating' => 5],
                ['author' => 'John Roe', 'body' => 'Cut defects fast.', 'rating' => 4],
                ['author' => 'Mei Lin', 'body' => 'Shipped in weeks.', 'rating' => 5],
            ]
        );

        $this->assertSame('Organization', $node['@type']);
        $this->assertSame('https://alisadikinma.com/#organization', $node['@id']);
        $this->assertSame('https://schema.org', $node['@context']);

        $this->assertArrayHasKey('aggregateRating', $node);
        $this->assertSame('AggregateRating', $node['aggregateRating']['@type']);
        $this->assertSame('4.7', $node['aggregateRating']['ratingValue']);
        $this->assertSame(3, $node['aggregateRating']['reviewCount']);
        $this->assertSame('5', $node['aggregateRating']['bestRating']);
        $this->assertSame('1', $node['aggregateRating']['worstRating']);

        $this->assertCount(3, $node['review']);
        $this->assertSame('Review', $node['review'][0]['@type']);
        $this->assertSame('Person', $node['review'][0]['author']['@type']);
        $this->assertSame('Jane Doe', $node['review'][0]['author']['name']);
        // HTML stripped from testimonial_text (it is stored as HTML).
        $this->assertSame('Great work on our line.', $node['review'][0]['reviewBody']);
        $this->assertStringNotContainsString('<', $node['review'][0]['reviewBody']);
        $this->assertSame('5', $node['review'][0]['reviewRating']['ratingValue']);
    }

    public function test_organizationWithRating_truncates_long_review_body(): void
    {
        $long = str_repeat('word ', 200); // ~1000 chars
        $node = $this->builder()->organizationWithRating(
            ['ratingValue' => 5, 'reviewCount' => 1],
            [['author' => 'Long Talker', 'body' => $long, 'rating' => 5]]
        );

        $this->assertLessThanOrEqual(281, mb_strlen($node['review'][0]['reviewBody']));
    }

    public function test_organizationWithRating_empty_input_emits_no_rating(): void
    {
        $node = $this->builder()->organizationWithRating(['reviewCount' => 0], []);

        // Still a real Organization node, but NO fabricated rating.
        $this->assertSame('Organization', $node['@type']);
        $this->assertSame('https://alisadikinma.com/#organization', $node['@id']);
        $this->assertArrayNotHasKey('aggregateRating', $node);
        $this->assertArrayNotHasKey('review', $node);
    }

    public function test_organizationWithRating_skips_reviews_missing_author_or_body(): void
    {
        $node = $this->builder()->organizationWithRating(
            ['ratingValue' => 4.5, 'reviewCount' => 2],
            [
                ['author' => '', 'body' => 'no author', 'rating' => 5],
                ['author' => 'Valid', 'body' => '   ', 'rating' => 4],
                ['author' => 'Real One', 'body' => 'A genuine review.', 'rating' => 5],
            ]
        );

        $this->assertCount(1, $node['review']);
        $this->assertSame('Real One', $node['review'][0]['author']['name']);
    }
}
