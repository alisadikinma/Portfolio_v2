<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use App\Models\PostTranslation;
use App\Support\HtmlFigureStripper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill historical `content_ideas.generated_article.{locale}` entries
 * from the authoritative `post_translations` rows. Two code paths wrote
 * translations to disjoint sinks:
 *
 *   - Automation (plugin /article-translate -> saveTranslation) wrote to
 *     post_translations only (before the saveTranslation mirror fix).
 *   - Admin (Finalize "Translate to English" -> translateArticle) wrote to
 *     generated_article only.
 *
 * The Finalize UI reads generated_article.{locale}.content (raw, then injects
 * images at render time from image_prompts). Ideas translated via the old
 * automation path had generated_article.en.content empty or a stale copy of
 * the primary-language content — Finalize saw "missing translation" and
 * auto-kicked off a wasteful re-translate every time the page was opened.
 *
 * This command walks every idea with a published post, fetches the matching
 * post_translations rows, strips figure blocks, and mirrors them back into
 * the idea JSON. Strictly conservative:
 *   - Primary-language slot is never touched (generated_article.{primary} is
 *     the raw authored source of truth).
 *   - Non-primary slots are written only when currently empty OR a duplicate
 *     of the primary (which is the signal that a translation was meant to
 *     happen but didn't mirror).
 *   - Figures are stripped before write, matching what translateArticle
 *     produces so Finalize doesn't double-render.
 *
 * Cosmetic only — never touches FSM, never re-translates, never hits Claude.
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
            $primaryLang = $article['language'] ?? 'id';
            $primaryContent = $article[$primaryLang]['content'] ?? '';
            $changedThisIdea = false;

            foreach ($translations as $pt) {
                $locale = $pt->language;

                // Guardrail 1: never touch the primary-language mirror.
                if ($locale === $primaryLang) {
                    continue;
                }

                $existing = $article[$locale] ?? [];
                $existingContent = $existing['content'] ?? '';

                // Guardrail 2: only fill missing or duplicate entries. If a
                // legit, different translation already lives in the idea JSON
                // (written by the admin translateArticle path), leave it alone.
                $isEmptyOrDup = $existingContent === '' || $existingContent === $primaryContent;
                if (!$isEmptyOrDup) {
                    continue;
                }

                $strippedContent = HtmlFigureStripper::strip($pt->content);

                // Already in sync after strip? skip.
                if ($existingContent === $strippedContent && $existingContent !== '') {
                    continue;
                }

                $totalDrift++;
                $this->line(sprintf(
                    '  idea=%d post=%d locale=%s mirror=%s',
                    $idea->id,
                    $idea->result_post_id,
                    $locale,
                    $existingContent === '' ? 'missing' : 'duplicate-of-primary'
                ));

                if ($this->option('dry-run')) {
                    continue;
                }

                $article[$locale] = array_merge($existing, [
                    'title' => $pt->title,
                    'content' => $strippedContent,
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
            $this->info("Dry-run: {$totalDrift} missing/duplicate translation mirror(s) detected. No changes written.");
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
