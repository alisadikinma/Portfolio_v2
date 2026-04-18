<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentPublishService
{
    public function __construct(private ArticleGenerationService $articleGen)
    {
    }

    /**
     * Publish a ContentIdea to a blog Post. Idempotent: upsert on source_idea_id.
     *
     * Returns a struct with the created Post and a translation_pending flag
     * so callers can decide their response shape.
     *
     * @throws \DomainException On invalid status or missing category.
     */
    public function publish(ContentIdea $idea): array
    {
        if (!in_array($idea->status, ['article_ready', 'images_ready', 'completed'])) {
            throw new \DomainException('Cannot publish in current status: ' . $idea->status);
        }

        $article = $idea->generated_article ?? [];
        $urlsToDelete = [];

        $imagePrompts = $this->compactVariations($article['image_prompts'] ?? [], $urlsToDelete);
        $article['image_prompts'] = $imagePrompts;
        $idea->generated_article = $article;
        $idea->save();

        $primaryLang = $article['language'] ?? 'id';
        $primary = $article[$primaryLang] ?? [];
        $title = $primary['title'] ?? $article['title'] ?? $idea->title;
        $rawContent = $primary['content'] ?? $article['content'] ?? '';
        $excerpt = $primary['excerpt'] ?? $article['excerpt'] ?? null;
        $content = $this->spliceBodyImagesIntoContent($rawContent, $imagePrompts);

        $otherLang = $primaryLang === 'id' ? 'en' : 'id';
        $otherContent = $article[$otherLang]['content'] ?? '';
        $hasRealTranslation = $otherContent !== '' && $otherContent !== $content;

        $coverPrompt = collect($imagePrompts)->firstWhere('type', 'cover');
        $featuredImage = data_get($idea->generated_images, '0.url')
            ?? data_get($idea->generated_images, '0')
            ?? data_get($coverPrompt, 'generated_url')
            ?? data_get($imagePrompts, '0.generated_url')
            ?? null;

        $categoryId = $this->resolveCategoryId($idea);
        if (!$categoryId) {
            throw new \DomainException('No category available. Create at least one category before publishing.');
        }

        $uniqueSlug = $this->buildUniqueSlug($title, $idea);

        $post = Post::updateOrCreate(
            ['source_idea_id' => $idea->id],
            [
                'category_id' => $categoryId,
                'slug' => $uniqueSlug,
                'featured_image' => $featuredImage,
                'published' => true,
                'published_at' => now(),
                'seo_score' => data_get($article, 'seo_analysis.score') ?? data_get($primary, 'seo_analysis.score'),
                'og_image' => data_get($primary, 'og_image') ?? data_get($article, 'og_image') ?? $featuredImage,
                'translation_pending' => !$hasRealTranslation,
                'translation_attempts' => 0,
            ]
        );

        $seo = $this->buildSeoDefaults($idea, $title, $excerpt, $content, $uniqueSlug, $primary, $article);

        PostTranslation::updateOrCreate(
            ['post_id' => $post->id, 'language' => $primaryLang],
            [
                'title' => $title,
                'slug' => $uniqueSlug,
                'excerpt' => $excerpt,
                'content' => $content,
                'meta_title' => $seo['meta_title'],
                'meta_description' => $seo['meta_description'],
                'meta_keywords' => $seo['meta_keywords'],
                'og_title' => data_get($primary, 'og_title') ?: data_get($article, 'og_title') ?: $seo['meta_title'],
                'og_description' => data_get($primary, 'og_description') ?: data_get($article, 'og_description') ?: $seo['meta_description'],
                'canonical_url' => $seo['canonical_url'],
                'ai_summary' => data_get($primary, 'ai_summary') ?? data_get($article, 'ai_summary'),
                'schema_markup' => data_get($primary, 'schema_markup') ?? data_get($article, 'schema_markup'),
                'faq_schema' => data_get($primary, 'faq_schema') ?? data_get($article, 'faq_schema'),
            ]
        );

        if ($hasRealTranslation) {
            $this->upsertSecondaryTranslation(
                $post, $idea, $otherLang, $article[$otherLang] ?? [],
                $title, $excerpt, $imagePrompts, $uniqueSlug, $primary, $article
            );
        }

        $this->autoPopulateRelatedPosts($post, $categoryId);
        $this->cleanupVariationFiles($urlsToDelete, $idea->id, $post->id);

        $translationPending = $this->triggerTranslationIfEnabled($idea, $post, $primaryLang);

        $idea->update([
            'status' => 'completed',
            'result_post_id' => $post->id,
        ]);

        return [
            'post' => $post,
            'translation_pending' => $translationPending,
        ];
    }

    private function compactVariations(array $imagePrompts, ?array &$urlsToDelete = null): array
    {
        $urlsToDelete = [];
        foreach ($imagePrompts as $i => $prompt) {
            $variations = $prompt['variations'] ?? [];
            if (empty($variations)) continue;

            $selectedIdx = $prompt['selected_variation'] ?? 0;
            $selectedVar = $variations[$selectedIdx] ?? $variations[0] ?? null;
            $selectedUrl = $selectedVar['url'] ?? ($prompt['generated_url'] ?? null);

            foreach ($variations as $vi => $v) {
                if ($vi !== $selectedIdx && !empty($v['url']) && $v['url'] !== $selectedUrl) {
                    $urlsToDelete[] = $v['url'];
                }
            }

            if ($selectedVar) {
                $imagePrompts[$i]['variations'] = [$selectedVar];
                $imagePrompts[$i]['selected_variation'] = 0;
                $imagePrompts[$i]['generated_url'] = $selectedUrl;
            }
        }
        return $imagePrompts;
    }

    private function resolveCategoryId(ContentIdea $idea): ?int
    {
        if (!empty($idea->niche)) {
            $category = Category::where('slug', Str::slug($idea->niche))
                ->orWhere('name', $idea->niche)
                ->first();
            if ($category) return $category->id;
        }
        return Category::orderBy('id')->value('id');
    }

    private function buildUniqueSlug(string $title, ContentIdea $idea): string
    {
        $baseSlug = Str::slug($title);
        $existing = Post::where('slug', $baseSlug)->where('source_idea_id', '!=', $idea->id)->first();
        return $existing ? ($baseSlug . '-' . $idea->id) : $baseSlug;
    }

    private function upsertSecondaryTranslation(
        Post $post,
        ContentIdea $idea,
        string $otherLang,
        array $secondary,
        string $fallbackTitle,
        ?string $fallbackExcerpt,
        array $imagePrompts,
        string $uniqueSlug,
        array $primary,
        array $article
    ): void {
        $secondaryTitle = $secondary['title'] ?? $fallbackTitle;
        $secondaryExcerpt = $secondary['excerpt'] ?? $fallbackExcerpt;
        $secondaryContent = $this->spliceBodyImagesIntoContent($secondary['content'] ?? '', $imagePrompts);
        $seoSecondary = $this->buildSeoDefaults(
            $idea, $secondaryTitle, $secondaryExcerpt, $secondaryContent, $uniqueSlug, $secondary, $article
        );

        PostTranslation::updateOrCreate(
            ['post_id' => $post->id, 'language' => $otherLang],
            [
                'title' => $secondaryTitle,
                'slug' => $uniqueSlug,
                'excerpt' => $secondaryExcerpt,
                'content' => $secondaryContent,
                'meta_title' => $seoSecondary['meta_title'],
                'meta_description' => $seoSecondary['meta_description'],
                'meta_keywords' => $seoSecondary['meta_keywords'],
                'og_title' => data_get($secondary, 'og_title') ?: $seoSecondary['meta_title'],
                'og_description' => data_get($secondary, 'og_description') ?: $seoSecondary['meta_description'],
                'canonical_url' => $seoSecondary['canonical_url'],
                'ai_summary' => data_get($secondary, 'ai_summary'),
                'schema_markup' => data_get($secondary, 'schema_markup') ?? data_get($primary, 'schema_markup'),
                'faq_schema' => data_get($secondary, 'faq_schema') ?? data_get($primary, 'faq_schema'),
            ]
        );
    }

    private function autoPopulateRelatedPosts(Post $post, int $categoryId): void
    {
        if ($post->relatedPosts()->count() > 0) return;

        $relatedIds = Post::where('category_id', $categoryId)
            ->where('id', '!=', $post->id)
            ->where('published', true)
            ->orderByDesc('published_at')
            ->limit(5)
            ->pluck('id');

        if ($relatedIds->isEmpty()) return;

        $syncData = [];
        foreach ($relatedIds as $idx => $rid) {
            $syncData[$rid] = ['sort_order' => $idx + 1];
        }
        $post->relatedPosts()->sync($syncData);
    }

    private function cleanupVariationFiles(array $urlsToDelete, int $ideaId, int $postId): void
    {
        if (empty($urlsToDelete)) return;

        $storageBase = url('/storage/');
        $deleted = 0;
        foreach ($urlsToDelete as $imageUrl) {
            if (!str_starts_with($imageUrl, $storageBase)) continue;
            $relativePath = str_replace($storageBase . '/', '', $imageUrl);
            try {
                if (Storage::disk('public')->exists($relativePath)) {
                    Storage::disk('public')->delete($relativePath);
                    $deleted++;
                }
            } catch (\Throwable $e) {
                Log::warning('[ContentPublish] Failed to delete variation file', [
                    'path' => $relativePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($deleted > 0) {
            Log::info('[ContentPublish] Cleaned up variation files on publish', [
                'idea_id' => $ideaId,
                'post_id' => $postId,
                'deleted_count' => $deleted,
                'requested_count' => count($urlsToDelete),
            ]);
        }
    }

    private function triggerTranslationIfEnabled(ContentIdea $idea, Post $post, string $primaryLang): bool
    {
        $targetLocales = array_values(array_diff($idea->languages ?? [$primaryLang], [$primaryLang]));
        if (
            !config('services.article_generation.use_translate_phase')
            || empty($targetLocales)
        ) {
            return false;
        }

        $targetLocale = $targetLocales[0];
        $idempotencyKey = (string) Str::uuid();
        $result = $this->articleGen->triggerTranslate($post->id, $idempotencyKey, $targetLocale);

        $post->update([
            'translation_pending' => true,
            'translation_attempts' => 1,
            'last_translation_attempt' => now(),
        ]);

        Log::info('[ContentPublish] Translation triggered', [
            'idea_id' => $idea->id,
            'post_id' => $post->id,
            'target_locale' => $targetLocale,
            'pid' => $result['pid'] ?? null,
        ]);

        return true;
    }

    private function buildSeoDefaults(
        ContentIdea $idea,
        string $title,
        ?string $excerpt,
        string $content,
        string $slug,
        array $langData,
        array $article
    ): array {
        $metaTitle = data_get($langData, 'meta_title') ?: data_get($article, 'meta_title');
        if (empty($metaTitle)) {
            $metaTitle = Str::limit(trim($title), 57, '...');
        }

        $metaDescription = data_get($langData, 'meta_description') ?: data_get($article, 'meta_description');
        if (empty($metaDescription)) {
            $source = $excerpt ?: strip_tags($content);
            $source = preg_replace('/\s+/', ' ', (string) $source);
            $metaDescription = Str::limit(trim($source), 155, '...');
        }

        $metaKeywords = data_get($langData, 'meta_keywords') ?: data_get($article, 'meta_keywords');
        if (empty($metaKeywords)) {
            $parts = array_filter([
                $idea->niche,
                $idea->pillar !== 'general' ? $idea->pillar : null,
            ]);
            if (is_array($idea->tags ?? null)) {
                foreach (array_slice($idea->tags, 0, 3) as $t) {
                    if (!empty($t)) $parts[] = $t;
                }
            }
            $metaKeywords = $parts ? implode(', ', array_unique(array_map('trim', $parts))) : null;
        }

        $canonicalUrl = data_get($langData, 'canonical_url') ?: data_get($article, 'canonical_url');
        if (empty($canonicalUrl)) {
            $frontendUrl = rtrim((string) config('app.frontend_url', 'https://alisadikinma.com'), '/');
            $canonicalUrl = $frontendUrl . '/blog/' . $slug;
        }

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'canonical_url' => $canonicalUrl,
        ];
    }

    private function spliceBodyImagesIntoContent(string $html, array $imagePrompts): string
    {
        if ($html === '' || empty($imagePrompts)) return $html;
        $blocks = $this->parseBlockElements($html);
        if (empty($blocks)) return $html;

        $totalAll = count($imagePrompts);
        $positioned = [];
        foreach ($imagePrompts as $origIndex => $img) {
            if (empty($img['generated_url']) || ($img['type'] ?? '') === 'cover') continue;
            $pos = $this->resolveImagePosition($img, $origIndex, $totalAll, $blocks);
            $positioned[] = ['img' => $img, 'pos' => $pos];
        }
        if (empty($positioned)) return $html;

        usort($positioned, fn ($a, $b) => $b['pos'] - $a['pos']);

        $blockHtml = array_column($blocks, 'html');
        foreach ($positioned as $p) {
            $img = $p['img'];
            $url = htmlspecialchars($img['generated_url'], ENT_QUOTES, 'UTF-8');
            $alt = htmlspecialchars($img['concept'] ?? '', ENT_QUOTES, 'UTF-8');
            $figure = '<figure class="my-8 not-prose">'
                . '<img src="' . $url . '" alt="' . $alt . '" class="w-full rounded-xl" loading="lazy" />'
                . '<figcaption class="text-sm text-neutral-500 dark:text-neutral-400 mt-2 text-center">' . $alt . '</figcaption>'
                . '</figure>';
            $safePos = max(0, min($p['pos'], count($blockHtml)));
            array_splice($blockHtml, $safePos, 0, [$figure]);
        }

        return implode("\n", $blockHtml);
    }

    private function parseBlockElements(string $html): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementsByTagName('div')->item(0);
        if (!$root) return [];

        $blocks = [];
        foreach ($root->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) continue;
            $blocks[] = [
                'tag' => strtoupper($node->nodeName),
                'text' => $node->textContent,
                'html' => $doc->saveHTML($node),
            ];
        }
        return $blocks;
    }

    private function resolveImagePosition(array $img, int $index, int $total, array $blocks): int
    {
        if (($img['type'] ?? '') === 'cover') return 0;
        if (isset($img['suggested_position']) && is_numeric($img['suggested_position'])) {
            return (int) $img['suggested_position'];
        }
        if (!empty($img['insert_after_heading']) && count($blocks) > 0) {
            $target = mb_strtolower(trim((string) $img['insert_after_heading']));
            foreach ($blocks as $i => $b) {
                if (preg_match('/^H[1-6]$/i', $b['tag'])) {
                    $text = mb_strtolower(trim($b['text']));
                    if ($text === $target || str_contains($text, $target) || str_contains($target, $text)) {
                        return $i + 1;
                    }
                }
            }
        }
        if (count($blocks) > 0 && $total > 0) {
            $step = (int) floor(count($blocks) / ($total + 1));
            return max(1, $step * ($index + 1));
        }
        return $index;
    }
}
