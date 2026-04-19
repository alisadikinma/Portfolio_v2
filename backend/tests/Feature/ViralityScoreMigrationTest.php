<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViralityScoreMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_idea_persists_virality_score_and_breakdown(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Test Viral Topic',
            'source' => 'google_news',
            'status' => 'draft',
            'virality_score' => 75,
            'virality_breakdown' => [
                'momentum' => 60,
                'virality' => 85,
                'triggers' => [
                    'social_currency' => true,
                    'high_arousal' => true,
                    'practical_utility' => false,
                    'identity_signaling' => true,
                    'cognitive_gap' => false,
                ],
            ],
        ]);

        $fresh = ContentIdea::find($idea->id);

        $this->assertSame(75, (int) $fresh->virality_score);
        $this->assertIsArray($fresh->virality_breakdown);
        $this->assertSame(60, $fresh->virality_breakdown['momentum']);
        $this->assertSame(85, $fresh->virality_breakdown['virality']);
        $this->assertTrue($fresh->virality_breakdown['triggers']['social_currency']);
        $this->assertFalse($fresh->virality_breakdown['triggers']['practical_utility']);
    }

    public function test_content_idea_allows_null_virality_fields(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Legacy topic without scores',
            'source' => 'manual',
            'status' => 'draft',
        ]);

        $fresh = ContentIdea::find($idea->id);

        $this->assertNull($fresh->virality_score);
        $this->assertNull($fresh->virality_breakdown);
    }
}
