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
    public function publish_does_not_throw_domain_exception_for_article_ready_status()
    {
        $idea = new ContentIdea();
        $idea->status = 'article_ready';

        // Will throw on a DIFFERENT exception (TypeError/QueryException when it
        // tries to touch DB) — but NOT a \DomainException from the status gate.
        try {
            $this->service->publish($idea);
        } catch (\DomainException $e) {
            $this->fail('publish() incorrectly rejected article_ready: ' . $e->getMessage());
        } catch (\Throwable $e) {
            // Other exceptions (DB, type, etc.) pass the status gate assertion
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function publish_does_not_throw_domain_exception_for_images_ready_status()
    {
        $idea = new ContentIdea();
        $idea->status = 'images_ready';

        try {
            $this->service->publish($idea);
        } catch (\DomainException $e) {
            $this->fail('publish() incorrectly rejected images_ready: ' . $e->getMessage());
        } catch (\Throwable $e) {
            // Non-status-gate exception — acceptable
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function publish_does_not_throw_domain_exception_for_completed_status()
    {
        $idea = new ContentIdea();
        $idea->status = 'completed';

        try {
            $this->service->publish($idea);
        } catch (\DomainException $e) {
            $this->fail('publish() incorrectly rejected completed: ' . $e->getMessage());
        } catch (\Throwable $e) {
            // Non-status-gate exception — acceptable
        }
        $this->assertTrue(true);
    }
}
