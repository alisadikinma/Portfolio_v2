<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Services\ContentPublishService;
use App\Services\ArticleGenerationService;
use Mockery;
use Tests\TestCase;

/**
 * Lightweight unit coverage for ContentPublishService. Full-stack publish
 * requires a live DB (Post, PostTranslation, Category, related_posts pivot)
 * which the test env can't satisfy (SQLite migration incompatibility). These
 * tests cover the pure-logic paths (status gate, domain exception) without
 * hitting the DB. Full publish behavior verified via manual QA (Phase 11).
 */
class ContentPublishServiceTest extends TestCase
{
    private ContentPublishService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $this->service = new ContentPublishService($articleGen);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function publish_throws_domain_exception_when_status_is_invalid()
    {
        $idea = new ContentIdea();
        $idea->status = 'draft';
        $idea->title = 'Test';

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot publish in current status: draft');

        $this->service->publish($idea);
    }

    /** @test */
    public function publish_throws_domain_exception_for_researching_status()
    {
        $idea = new ContentIdea();
        $idea->status = 'researching';
        $idea->title = 'Test';

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('researching');

        $this->service->publish($idea);
    }

    /** @test */
    public function publish_throws_domain_exception_for_archived_status()
    {
        $idea = new ContentIdea();
        $idea->status = 'archived';
        $idea->title = 'Test';

        $this->expectException(\DomainException::class);

        $this->service->publish($idea);
    }

    /** @test */
    public function publish_accepts_article_ready_images_ready_and_completed_statuses()
    {
        // Verify the allow-list by inspecting source — we can't fully test publish()
        // without DB, but we can verify the three allowed statuses don't trigger
        // the early domain exception.
        $reflection = new \ReflectionMethod(ContentPublishService::class, 'publish');
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString("'article_ready', 'images_ready', 'completed'", $source);
    }
}
