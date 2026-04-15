<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use App\Models\Post;
use App\Services\ArticleGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProcessPendingTranslations extends Command
{
    protected $signature = 'content:process-pending-translations';

    protected $description = 'Retry failed translations for published posts (runs every 5min, max 3 attempts per post)';

    public function handle(ArticleGenerationService $service): int
    {
        $posts = Post::where('translation_pending', true)
            ->where(function ($q) {
                $q->whereNull('last_translation_attempt')
                    ->orWhere('last_translation_attempt', '<=', now()->subMinutes(5));
            })
            ->where('translation_attempts', '<', 3)
            ->limit(5)
            ->get();

        foreach ($posts as $post) {
            $idea = $post->source_idea_id
                ? ContentIdea::find($post->source_idea_id)
                : null;

            $primaryLang = data_get($idea?->generated_article, 'language', 'id');
            $targetLocales = array_values(array_diff(
                $idea?->languages ?? ['en'],
                [$primaryLang]
            ));

            if (empty($targetLocales)) {
                $post->update(['translation_pending' => false]);
                $this->line("  Post #{$post->id} has no target locales — marked non-pending");
                continue;
            }

            $key = (string) Str::uuid();
            $result = $service->triggerTranslate($post->id, $key, $targetLocales[0]);

            $post->update([
                'translation_attempts' => $post->translation_attempts + 1,
                'last_translation_attempt' => now(),
            ]);

            $this->line("  Retry translate for post #{$post->id} (attempt {$post->translation_attempts}, pid {$result['pid']})");
        }

        // Posts that exhausted 3 attempts → flip flag off for manual review
        $giveUpCount = Post::where('translation_pending', true)
            ->where('translation_attempts', '>=', 3)
            ->count();

        if ($giveUpCount > 0) {
            Post::where('translation_pending', true)
                ->where('translation_attempts', '>=', 3)
                ->update(['translation_pending' => false]);
            $this->warn("Gave up on {$giveUpCount} post(s) after 3 attempts — needs manual review");
        }

        $this->info("Processed {$posts->count()} pending translation(s)");
        return self::SUCCESS;
    }
}
