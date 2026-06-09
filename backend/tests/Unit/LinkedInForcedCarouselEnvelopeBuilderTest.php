<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\CarouselGenOutputAdapter;
use App\Services\LinkedInGenerationService;
use App\Services\PipelineGuard;
use Mockery;
use Tests\TestCase;

/**
 * Phase A — skip-linkedin-gen plan (2026-06-09).
 * Unit tests for the pure LinkedInGenerationService::buildForcedCarouselEnvelope().
 * No DB / no SSH — relations set in-memory via setRelation(). The synthetic
 * envelope must be byte-shape-compatible with what forceCarouselEnvelope()
 * produces so the existing applyCarouselGenAdapter + persistAndRoute path runs
 * unchanged when /linkedin-gen is skipped under force-carousel.
 */
class LinkedInForcedCarouselEnvelopeBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): LinkedInGenerationService
    {
        $guard = Mockery::mock(PipelineGuard::class);
        return new LinkedInGenerationService($guard, new CarouselGenOutputAdapter());
    }

    public function test_builds_route_to_carousel_gen_envelope_with_pillar_from_idea(): void
    {
        $svc = $this->makeService();

        $idea = new ContentIdea();
        $idea->pillar = 'vibe_coding';
        $post = new Post();
        $post->setRelation('contentIdea', $idea);
        $draft = new LinkedInPost();
        $draft->setRelation('post', $post);

        $out = $svc->buildForcedCarouselEnvelope($draft);

        $this->assertSame('carousel', $out['format']);
        $this->assertSame('route_to_carousel_gen', $out['status']);
        $this->assertNull($out['carousel']);
        $this->assertNull($out['post']);
        $this->assertNull($out['validation']);
        $this->assertSame('vibe_coding', $out['brief']['pillar']);
    }

    public function test_falls_back_to_default_pillar_when_idea_absent(): void
    {
        $svc = $this->makeService();

        // No relations loaded — builder must NOT trigger a lazy DB query and
        // must fall back to the default pillar.
        $draft = new LinkedInPost();

        $out = $svc->buildForcedCarouselEnvelope($draft);

        $this->assertSame('carousel', $out['format']);
        $this->assertSame('route_to_carousel_gen', $out['status']);
        $this->assertSame('ai_generalist', $out['brief']['pillar']);
        $this->assertNull($out['carousel']);
        $this->assertNull($out['post']);
        $this->assertNull($out['validation']);
    }

    public function test_falls_back_when_idea_pillar_empty(): void
    {
        $svc = $this->makeService();

        $idea = new ContentIdea();
        $idea->pillar = '';
        $post = new Post();
        $post->setRelation('contentIdea', $idea);
        $draft = new LinkedInPost();
        $draft->setRelation('post', $post);

        $out = $svc->buildForcedCarouselEnvelope($draft);

        $this->assertSame('ai_generalist', $out['brief']['pillar']);
    }
}
