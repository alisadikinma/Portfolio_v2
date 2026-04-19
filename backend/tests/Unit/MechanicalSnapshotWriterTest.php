<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Services\MechanicalSnapshotWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MechanicalSnapshotWriterTest extends TestCase
{
    use RefreshDatabase;

    private function makeIdeaWithArticle(array $articleOverride = []): ContentIdea
    {
        $article = array_merge([
            'title' => 'Best AI Coding Tools 2026',
            'content' => "<h2>Intro</h2><p>We discuss ai coding tools for developers in 2025 and 2026. "
                . "Claude Code is one of the best ai coding tools for serious developers.</p>"
                . "<h2>Top Picks</h2><p>Cursor and Windsurf lead the field.</p>",
            'keyword' => 'ai coding tools',
            'language' => 'en',
        ], $articleOverride);

        return ContentIdea::create([
            'title' => $article['title'],
            'source' => 'manual',
            'status' => 'draft',
            'generated_article' => $article,
        ]);
    }

    public function test_capture_for_persists_snapshot_and_returns_payload(): void
    {
        $idea = $this->makeIdeaWithArticle();

        $writer = app(MechanicalSnapshotWriter::class);
        $payload = $writer->captureFor($idea);

        // Return value shape
        $this->assertArrayHasKey('captured_at', $payload);
        $this->assertArrayHasKey('word_count', $payload);
        $this->assertArrayHasKey('seo', $payload);
        $this->assertArrayHasKey('ai_humanization', $payload);
        $this->assertArrayHasKey('faq_pair_count', $payload);
        $this->assertArrayHasKey('freshness_signals', $payload);
        $this->assertArrayHasKey('h2_count', $payload);

        // captured_at is a parseable ISO-8601 timestamp
        $this->assertNotNull(strtotime($payload['captured_at']));

        // SEO subkeys present
        $this->assertArrayHasKey('title_length', $payload['seo']);
        $this->assertArrayHasKey('keyword_in_title', $payload['seo']);
        $this->assertTrue($payload['seo']['keyword_in_title']['value']);

        // Persistence side-effect
        $fresh = ContentIdea::find($idea->id);
        $this->assertIsArray($fresh->mechanical_scores_snapshot);
        $this->assertSame($payload['word_count'], $fresh->mechanical_scores_snapshot['word_count']);
        $this->assertSame($payload['captured_at'], $fresh->mechanical_scores_snapshot['captured_at']);
    }

    public function test_capture_for_returns_error_and_skips_persist_when_article_missing(): void
    {
        $idea = ContentIdea::create([
            'title' => 'No article yet',
            'source' => 'manual',
            'status' => 'draft',
        ]);

        $writer = app(MechanicalSnapshotWriter::class);
        $payload = $writer->captureFor($idea);

        $this->assertArrayHasKey('error', $payload);
        $this->assertStringContainsString('missing', strtolower($payload['error']));

        $fresh = ContentIdea::find($idea->id);
        $this->assertNull($fresh->mechanical_scores_snapshot);
    }

    public function test_capture_for_indonesian_article_includes_language_note(): void
    {
        $idea = $this->makeIdeaWithArticle([
            'title' => 'Alat AI untuk Pengembang Indonesia',
            'content' => '<h2>Pendahuluan</h2><p>Kami membahas alat ai terbaik.</p>',
            'keyword' => 'alat ai',
            'language' => 'id',
        ]);

        $writer = app(MechanicalSnapshotWriter::class);
        $payload = $writer->captureFor($idea);

        $this->assertArrayHasKey('ai_humanization', $payload);
        $this->assertArrayHasKey('note', $payload['ai_humanization']);
        $this->assertStringContainsString('Indonesian', $payload['ai_humanization']['note']);
        $this->assertSame('id', $payload['language']);
    }

    public function test_capture_for_is_idempotent_and_overwrites_prior_snapshot(): void
    {
        $idea = $this->makeIdeaWithArticle();

        $writer = app(MechanicalSnapshotWriter::class);

        $first = $writer->captureFor($idea);
        // Mutate the article between runs
        $idea->update([
            'generated_article' => array_merge($idea->generated_article, [
                'content' => str_repeat('<p>Extra content. </p>', 40),
            ]),
        ]);

        $second = $writer->captureFor($idea->fresh());

        $this->assertNotSame($first['word_count'], $second['word_count']);
        $fresh = ContentIdea::find($idea->id);
        $this->assertSame($second['word_count'], $fresh->mechanical_scores_snapshot['word_count']);
    }
}
