<?php

namespace App\Jobs;

use App\Models\ContentIdea;
use App\Services\ArticleGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around ArticleGenerationService::translateArticle() for the
 * Content Engine Finalize step (idea-level, pre-publish — no Post exists yet).
 *
 * Why async: the underlying SSH → Claude CLI inline translate of a full article
 * runs 90-300s+. The old synchronous controller endpoint routinely exceeded
 * Cloudflare's ~100s edge timeout → browser got 524 on every real translate,
 * even when the backend eventually succeeded. Now the controller sets
 * translation_status='translating', dispatches this job, and returns 202; the
 * frontend polls generated_article.translation_status until done/failed.
 *
 * tries=1: a translate is an expensive LLM call — a blind retry would double the
 * cost. Operator re-clicks "Retry translation" on genuine failure.
 */
class TranslateContentIdea implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // headroom past the 300s inline Process cap for big articles
    public int $tries = 1;

    public function __construct(public int $ideaId)
    {
    }

    public function handle(ArticleGenerationService $articleGen): void
    {
        $idea = ContentIdea::find($this->ideaId);
        if (!$idea) {
            Log::warning('[TranslateContentIdea] idea not found', ['idea_id' => $this->ideaId]);
            return;
        }

        $article = $idea->generated_article ?? [];
        $primaryLang = $article['language'] ?? 'id';
        $targetLang = $primaryLang === 'id' ? 'en' : 'id';
        $primary = $article[$primaryLang] ?? [];

        if (empty($primary['title']) && empty($primary['content'])) {
            $this->markFailed($idea, 'Primary-language article content is missing; nothing to translate.');
            return;
        }

        $result = $articleGen->translateArticle($primary);

        // Re-fetch to avoid clobbering concurrent writes (webhooks, etc.)
        $idea->refresh();
        $article = $idea->generated_article ?? [];

        if (!($result['success'] ?? false)) {
            $article['translation_status'] = 'failed';
            $article['translation_error'] = $result['error'] ?? 'Unknown error';
            $idea->generated_article = $article;
            $idea->save();
            return;
        }

        $translated = $result['translated'];
        // Non-translated fields copied from primary (language-agnostic)
        $article[$targetLang] = array_merge($article[$targetLang] ?? [], [
            'title' => $translated['title'] ?? '',
            'content' => $translated['content'] ?? '',
            'excerpt' => $translated['excerpt'] ?? '',
            'meta_title' => $translated['meta_title'] ?? '',
            'meta_description' => $translated['meta_description'] ?? '',
            'og_title' => $translated['og_title'] ?? '',
            'og_description' => $translated['og_description'] ?? '',
            'ai_summary' => $translated['ai_summary'] ?? '',
            'schema_markup' => $primary['schema_markup'] ?? ($article[$targetLang]['schema_markup'] ?? null),
            'faq_schema' => $primary['faq_schema'] ?? ($article[$targetLang]['faq_schema'] ?? null),
            'canonical_url' => $primary['canonical_url'] ?? ($article[$targetLang]['canonical_url'] ?? null),
        ]);
        $article['translation_status'] = 'done';
        $article['translation_completed_at'] = now()->toIso8601String();
        unset($article['translation_error']);
        $idea->generated_article = $article;
        $idea->save();
    }

    // Queue-level crash (timeout, worker kill) — surface as a failed status the UI polls.
    public function failed(\Throwable $e): void
    {
        $idea = ContentIdea::find($this->ideaId);
        if ($idea) {
            $this->markFailed($idea, $e->getMessage());
        }
    }

    private function markFailed(ContentIdea $idea, string $error): void
    {
        $idea->refresh();
        $article = $idea->generated_article ?? [];
        $article['translation_status'] = 'failed';
        $article['translation_error'] = $error;
        $idea->generated_article = $article;
        $idea->save();
    }
}
