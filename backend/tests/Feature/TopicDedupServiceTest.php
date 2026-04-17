<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Services\TopicDedupService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class TopicDedupServiceTest extends TestCase
{
    private TopicDedupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TopicDedupService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Stub ContentIdea::where(...)->get(...) to return a controlled collection
     * so the dedup scan runs against in-memory rows without hitting the DB.
     */
    private function mockRecentIdeas(array $rows): void
    {
        $collection = new Collection(array_map(function ($row) {
            $idea = new ContentIdea();
            $idea->id = $row['id'] ?? random_int(1, 10000);
            $idea->title = $row['title'];
            return $idea;
        }, $rows));

        $query = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $query->shouldReceive('get')->andReturn($collection);

        $mock = Mockery::mock('alias:' . ContentIdea::class);
        $mock->shouldReceive('where')
            ->with('created_at', '>=', Mockery::any())
            ->andReturn($query);
    }

    /** @test */
    public function returns_null_when_no_recent_ideas()
    {
        $this->mockRecentIdeas([]);

        $this->assertNull($this->service->isDuplicate('Fresh AI Topic'));
    }

    /** @test */
    public function detects_exact_slug_match()
    {
        $this->mockRecentIdeas([
            ['id' => 1, 'title' => 'AI Coding Tools'],
            ['id' => 2, 'title' => 'Google Gemini Update'],
        ]);

        $match = $this->service->isDuplicate('AI Coding Tools');

        $this->assertNotNull($match);
        $this->assertSame(1, $match->id);
    }

    /** @test */
    public function detects_slug_match_case_insensitive()
    {
        $this->mockRecentIdeas([
            ['id' => 5, 'title' => 'AI CODING tools'],
        ]);

        $match = $this->service->isDuplicate('ai coding TOOLS');

        $this->assertNotNull($match);
        $this->assertSame(5, $match->id);
    }

    /** @test */
    public function detects_high_similarity_match_above_threshold()
    {
        $this->mockRecentIdeas([
            ['id' => 10, 'title' => 'AI Coding Tools for Developers'],
        ]);

        // Very similar — similar_text should return >= 85%
        $match = $this->service->isDuplicate('AI Coding Tools for Developer');

        $this->assertNotNull($match);
        $this->assertSame(10, $match->id);
    }

    /** @test */
    public function returns_null_for_low_similarity_titles()
    {
        $this->mockRecentIdeas([
            ['id' => 1, 'title' => 'AI Coding Tools'],
            ['id' => 2, 'title' => 'Gemini Release Notes'],
        ]);

        // Completely different topic — should NOT trigger dedup
        $this->assertNull($this->service->isDuplicate('How to Grow Tomatoes'));
    }

    /** @test */
    public function empty_title_returns_null()
    {
        $this->mockRecentIdeas([
            ['id' => 1, 'title' => 'AI Coding Tools'],
        ]);

        $this->assertNull($this->service->isDuplicate(''));
        $this->assertNull($this->service->isDuplicate('   '));
    }
}
