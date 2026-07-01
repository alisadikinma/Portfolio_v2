<?php

namespace Tests\Feature;

use App\Jobs\TranslateContentIdea;
use App\Models\ContentIdea;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * The queued translate wrapper writes the target-locale slot + a terminal
 * translation_status the frontend polls. Mirrors the sync merge logic that
 * used to live in ContentIdeaController::translateArticle (now dispatch-only).
 */
class TranslateContentIdeaJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeIdea(): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'Job idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'completed',
            'auto_mode' => true,
            'generated_article' => [
                'language' => 'id',
                'id' => ['title' => 'Judul ID', 'content' => '<p>Isi ID</p>'],
                'translation_status' => 'translating',
            ],
        ]);
    }

    /** @test */
    public function success_writes_en_slot_and_done_status(): void
    {
        $idea = $this->makeIdea();

        $svc = Mockery::mock(ArticleGenerationService::class);
        $svc->shouldReceive('translateArticle')->once()->andReturn([
            'success' => true,
            'translated' => ['title' => 'Title EN', 'content' => '<p>Content EN</p>'],
            'error' => null,
        ]);

        (new TranslateContentIdea($idea->id))->handle($svc);

        $art = $idea->fresh()->generated_article;
        $this->assertSame('done', $art['translation_status']);
        $this->assertSame('Title EN', $art['en']['title']);
        $this->assertArrayNotHasKey('translation_error', $art);
    }

    /** @test */
    public function failure_writes_failed_status_with_error(): void
    {
        $idea = $this->makeIdea();

        $svc = Mockery::mock(ArticleGenerationService::class);
        $svc->shouldReceive('translateArticle')->once()->andReturn([
            'success' => false,
            'translated' => null,
            'error' => 'SSH boom',
        ]);

        (new TranslateContentIdea($idea->id))->handle($svc);

        $art = $idea->fresh()->generated_article;
        $this->assertSame('failed', $art['translation_status']);
        $this->assertSame('SSH boom', $art['translation_error']);
        $this->assertArrayNotHasKey('en', $art);
    }
}
