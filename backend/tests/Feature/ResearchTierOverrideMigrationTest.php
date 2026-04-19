<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchTierOverrideMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_idea_persists_research_tier_override(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Tier override persistence',
            'source' => 'manual',
            'status' => 'draft',
            'research_tier_override' => 'deep',
        ]);

        $this->assertSame('deep', $idea->fresh()->research_tier_override);
    }

    public function test_content_idea_research_tier_override_defaults_to_auto(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Default tier override',
            'source' => 'manual',
            'status' => 'draft',
        ]);

        $this->assertSame('auto', $idea->fresh()->research_tier_override);
    }

    public function test_content_idea_rejects_invalid_research_tier_override(): void
    {
        $this->expectException(QueryException::class);

        ContentIdea::create([
            'title' => 'Invalid tier override',
            'source' => 'manual',
            'status' => 'draft',
            'research_tier_override' => 'turbo',
        ]);
    }
}
