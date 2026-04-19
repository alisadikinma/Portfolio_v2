<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MechanicalScoresSnapshotMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_idea_persists_mechanical_scores_snapshot(): void
    {
        $snapshot = [
            'captured_at' => '2026-04-19T10:00:00+00:00',
            'word_count' => 1842,
            'seo' => [
                'title_length' => ['value' => 52, 'status' => 'green'],
                'keyword_in_title' => ['value' => true, 'status' => 'green'],
                'title_word_count' => ['value' => 8, 'status' => 'green'],
                'body_keyword_density' => ['value' => 1.2, 'occurrences' => 22, 'status' => 'green'],
                'keyword_in_first_100' => ['value' => true, 'status' => 'green'],
                'keyword_in_headings' => ['value' => 2, 'status' => 'green'],
            ],
            'freshness_signals' => 3,
            'faq_pair_count' => 5,
            'h2_count' => 8,
            'h3_count' => 12,
            'ai_humanization' => [
                'tier1_violations' => [],
                'tier1_violation_count' => 0,
                'tier2_clusters' => 1,
                'tier2_cluster_paragraphs' => [],
                'tier3_density_issues' => [],
            ],
            'language' => 'en',
        ];

        $idea = ContentIdea::create([
            'title' => 'Test snapshot persistence',
            'source' => 'manual',
            'status' => 'draft',
            'mechanical_scores_snapshot' => $snapshot,
        ]);

        $fresh = ContentIdea::find($idea->id);

        $this->assertIsArray($fresh->mechanical_scores_snapshot);
        $this->assertSame(1842, $fresh->mechanical_scores_snapshot['word_count']);
        $this->assertSame('green', $fresh->mechanical_scores_snapshot['seo']['title_length']['status']);
        $this->assertSame(52, $fresh->mechanical_scores_snapshot['seo']['title_length']['value']);
        $this->assertSame(5, $fresh->mechanical_scores_snapshot['faq_pair_count']);
        $this->assertSame(0, $fresh->mechanical_scores_snapshot['ai_humanization']['tier1_violation_count']);
        $this->assertSame('2026-04-19T10:00:00+00:00', $fresh->mechanical_scores_snapshot['captured_at']);
    }

    public function test_content_idea_allows_null_mechanical_scores_snapshot(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Legacy idea without snapshot',
            'source' => 'manual',
            'status' => 'draft',
        ]);

        $fresh = ContentIdea::find($idea->id);

        $this->assertNull($fresh->mechanical_scores_snapshot);
    }
}
