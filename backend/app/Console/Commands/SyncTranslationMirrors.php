<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use App\Models\PostTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill historical `content_ideas.generated_article.{locale}` entries
 * from the authoritative `post_translations` rows. Two code paths write
 * translations to disjoint sinks:
 *
 *   - Automation (plugin /article-translate → saveTranslation) writes to
 *     post_translations only.
 *   - Admin (Finalize "Translate to English" → translateArticle) writes to
 *     generated_article only.
 *
 * Before the mirror-write fix in saveTranslation, any idea translated via
 * the automation path had no generated_article.{locale} blob. The Finalize
 * UI reads that blob and showed "Belum diterjemahkan" for posts already
 * translated + published to the blog.
 *
 * This command walks every idea with a published post, fetches the matching
 * post_translations rows, and mirrors each translation back into the idea's
 * generated_article JSON. Cosmetic only — never touches FSM, never
 * re-translates, never hits Claude.
 *
 * Run as: php artisan content-engine:sync-translation-mirrors [--dry-run] [--idea=N]
 */
class SyncTranslationMirrors extends Command
{
    protected $signature = 'content-engine:sync-translation-mirrors
        {--dry-run : Print intended changes without writing}
        {--idea= : Limit to a single content_idea ID}';

    protected $description = 'Backfill content_ideas.generated_article.{locale} from post_translations rows';

    public function handle(): int
    {
        $query = ContentIdea::query()
            ->whereNotNull('result_post_id');

        if ($ideaId = $this->option('idea')) {
            $query->where('id', (int) $ideaId);
        }

        $ideas = $query->get();
        $this->info("Scanning {$ideas->count()} ideas with published posts...");

        $totalDrift = 0;
        $totalFixed = 0;

        foreach ($ideas as $idea) {
            $translations = PostTranslation::where('post_id', $idea->result_post_id)->get();
            if ($translations->isEmpty()) {
                continue;
            }

            $article = $idea->generated_article ?? [];
            $changedThisIdea = false;

            foreach ($translations as $pt) {
                $locale = $pt->language;
                $existing = $article[$locale] ?? [];
                $existingContent = $existing['content'] ?? '';

                if ($existingContent === $pt->content && $existingContent !== '') {
                    continue;
                }

                $totalDrift++;
                $this->line(sprintf(
                    '  idea=%d post=%d locale=%s mirror=%s',
                    $idea->id,
                    $idea->result_post_id,
                    $locale,
                    $existingContent === '' ? 'missing' : 'stale'
                ));

                if ($this->option('dry-run')) {
                    continue;
                }

                $article[$locale] = array_merge($existing, [
                    'title' => $pt->title,
                    'content' => $pt->content,
                    'excerpt' => $pt->excerpt,
                    'meta_title' => $pt->meta_title,
                    'meta_description' => $pt->meta_description,
                    'og_title' => $pt->og_title,
                    'og_description' => $pt->og_description,
                    'ai_summary' => $pt->ai_summary,
                ]);
                $changedThisIdea = true;
                $totalFixed++;
            }

            if ($changedThisIdea) {
                $article['translation_status'] = 'done';
                $article['translation_completed_at'] = now()->toIso8601String();
                unset($article['translation_error']);
                $idea->generated_article = $article;
                $idea->save();
            }
        }

        $this->newLine();
        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$totalDrift} missing/stale translation mirror(s) detected. No changes written.");
        } else {
            $this->info("Mirrored {$totalFixed} translation(s) across all ideas.");
            Log::info('[SyncTranslationMirrors] Backfilled generated_article translations', [
                'drift_count' => $totalDrift,
                'fixed_count' => $totalFixed,
                'limited_to_idea' => $this->option('idea'),
            ]);
        }

        return 0;
    }
}
