<?php

namespace App\Services;

use App\Enums\ContentIdeaStatus;
use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Support\HtmlSlashSanitizer;
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

        // Defense-in-depth: sanitize JSON-escape leakage (`\/` → `/`) on the
        // final-hop article data before we copy it into post_translations.
        // Every upstream writer (/complete, save-article, save-translation,
        // triggerTranslatePreflight) already does this, but applying here
        // too guarantees that any legacy idea (e.g. published before the
        // sanitizer was wired everywhere) gets clean HTML in the Post.
        $article = HtmlSlashSanitizer::apply($idea->generated_article ?? []);
        $urlsToDelete = [];

        $imagePrompts = $this->compactVariations($article['image_prompts'] ?? [], $urlsToDelete);
        $article['image_prompts'] = $imagePrompts;
        $idea->generated_article = $article;
        $idea->save();

        // Primary language resolution — plugin SHOULD set $article['language']
        // at top-level, but older pipelines or plugin bugs sometimes store
        // content under a language key (e.g. 'id' or 'en') without the flag.
        // Fallback chain: explicit language field → first language key that
        // actually contains content → 'id' default.
        $primaryLang = $article['language'] ?? null;
        if (!$primaryLang || empty($article[$primaryLang]['content'] ?? '')) {
            foreach (['id', 'en'] as $candidate) {
                if (!empty($article[$candidate]['content'] ?? '')) {
                    $primaryLang = $candidate;
                    break;
                }
            }
            $primaryLang = $primaryLang ?? 'id';
        }
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

        $categoryId = $this->resolveCategoryId($idea, $title, $primary, $article);
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

        // Transition to completed via FSM when not already there (idempotency
        // guard — direct admin publish call on a `completed` idea re-runs
        // this method; completed → completed is not in TRANSITIONS).
        if ($idea->status !== 'completed') {
            $idea->transitionTo(ContentIdeaStatus::Completed, 'publish_service', [
                'result_post_id' => $post->id,
            ]);
        } else {
            $idea->update(['result_post_id' => $post->id]);
        }

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

    /**
     * Resolve the best-fit category for an idea.
     *
     * Order of precedence:
     *   1. Exact niche match (slug or name)
     *   2. Exact pillar match (slug or name)
     *   3. Token-overlap scoring — score every category by counting how many
     *      of its name/slug tokens appear in the article's title + tags +
     *      meta_keywords + excerpt. The highest non-zero score wins.
     *   4. A "generic" category (general / uncategorized / other / news) if one exists
     *   5. Log a warning and return the newest category (less biased than oldest)
     */
    private function resolveCategoryId(
        ContentIdea $idea,
        string $title = '',
        array $primary = [],
        array $article = []
    ): ?int {
        $categories = Category::all(['id', 'name', 'slug']);
        if ($categories->isEmpty()) return null;
        if ($categories->count() === 1) return $categories->first()->id;

        if (!empty($idea->niche)) {
            $match = $categories->first(fn ($c) =>
                $c->slug === Str::slug($idea->niche) || strcasecmp($c->name, $idea->niche) === 0
            );
            if ($match) return $match->id;
        }

        if (!empty($idea->pillar) && $idea->pillar !== 'general') {
            $match = $categories->first(fn ($c) =>
                $c->slug === Str::slug($idea->pillar) || strcasecmp($c->name, $idea->pillar) === 0
            );
            if ($match) return $match->id;
        }

        $haystack = Str::lower(implode(' ', array_filter([
            $title,
            $idea->title,
            is_array($idea->tags) ? implode(' ', $idea->tags) : '',
            data_get($primary, 'meta_keywords') ?: data_get($article, 'meta_keywords') ?: '',
            data_get($primary, 'meta_title') ?: '',
            data_get($primary, 'excerpt') ?: '',
        ])));
        $haystackTokens = array_filter(preg_split('/[^a-z0-9]+/', $haystack));

        if (!empty($haystackTokens)) {
            $scored = $categories->map(function ($c) use ($haystackTokens) {
                $needles = array_filter(array_unique(array_merge(
                    preg_split('/[^a-z0-9]+/', Str::lower($c->name)) ?: [],
                    preg_split('/[^a-z0-9]+/', Str::lower($c->slug)) ?: []
                )));
                $needles = array_filter($needles, fn ($t) => strlen($t) >= 3);
                $score = 0;
                foreach ($needles as $n) {
                    $score += count(array_keys($haystackTokens, $n));
                }
                return ['id' => $c->id, 'score' => $score];
            })->sortByDesc('score')->values();

            if ($scored[0]['score'] > 0) return $scored[0]['id'];
        }

        foreach (['general', 'uncategorized', 'other', 'news', 'technology', 'ai'] as $fallbackSlug) {
            $generic = $categories->first(fn ($c) => $c->slug === $fallbackSlug);
            if ($generic) return $generic->id;
        }

        Log::warning('ContentPublishService: no category match — falling back to newest category', [
            'idea_id' => $idea->id,
            'idea_title' => $idea->title,
            'idea_niche' => $idea->niche,
            'idea_pillar' => $idea->pillar,
        ]);

        return Category::orderByDesc('id')->value('id');
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

        // Keyword resolution priority (most specific → least specific):
        //   1. Plugin-authored per-language meta_keywords      (best — already
        //      a comma-separated list of 3-6 terms per /article-write spec)
        //   2. Plugin-authored top-level meta_keywords
        //   3. Synthesized from target_keyword + extracted proper nouns from
        //      title (covers the common case where the plugin only emits the
        //      singular SEO target like "gugatan Musk vs Altman OpenAI 2026"
        //      without related/LSI terms)
        //   4. niche + pillar + tags fallback (legacy generic chain)
        $metaKeywords = data_get($langData, 'meta_keywords')
            ?: data_get($article, 'meta_keywords');

        if (empty($metaKeywords)) {
            // Web SEO best practice (Bing/Yandex era — Google deprecated
            // ranking weight in 2009 but still indexes meta_keywords for
            // internal search/discovery, AI crawlers, and llms.txt parsers):
            //
            //   - 5-8 short keywords (1-3 words each, NEVER full sentences)
            //   - Entity-focused: brand names, person names, product names,
            //     topic keywords. Each keyword should be something a user
            //     would actually type into a search box.
            //   - Avoid stuffing the target_keyword phrase verbatim — that
            //     long-tail SEO phrase belongs in title + meta_title, not
            //     in the meta_keywords list. Including it here hurts
            //     scannability and bloats the tag.
            //
            // Strategy: extract clean entity tokens from article body lede,
            // optionally prepend a broad topic keyword (category name or
            // pillar) for discoverability. Skip the target_keyword phrase
            // entirely — it's redundant with title/meta_title.
            $primary = data_get($article, 'target_keyword')
                ?: data_get($article, 'seo_analysis.keyword')
                ?: data_get($article, 'prep_data.keyword');
            $bodySource = (string) ($content ?? '');
            $extracted = $this->extractKeywordTerms($bodySource, (string) $primary);

            $keywords = [];

            // Broad topic keyword first — gives the list a discoverability
            // anchor for users searching by topic ("AI", "Tech", "Business").
            // Pulled from idea.niche when meaningful, else first word of pillar.
            $broadTopic = $this->resolveBroadTopic($idea);
            if ($broadTopic !== null) {
                $keywords[] = $broadTopic;
            }

            // Then extracted entities — these are the high-signal SEO tokens.
            // Cap at 7 total so the meta tag stays tight + scannable.
            foreach ($extracted as $term) {
                if (count($keywords) >= 7) break;
                if (in_array(mb_strtolower($term), array_map('mb_strtolower', $keywords), true)) continue;
                $keywords[] = $term;
            }

            $metaKeywords = !empty($keywords) ? implode(', ', $keywords) : null;
        }

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

    /**
     * Map idea pillar/niche to a single broad-topic keyword for the meta_keywords
     * list anchor. Returns null when no clean mapping exists (skips rather
     * than emit garbage). Pulled from a small explicit map so the output
     * is predictable across the catalog — no per-idea string parsing.
     */
    private function resolveBroadTopic(\App\Models\ContentIdea $idea): ?string
    {
        $pillarMap = [
            'vibe_coding' => 'AI Coding',
            'ai_automation' => 'AI Automation',
            'ai_agents' => 'AI Agents',
            'ai_video_image' => 'AI Media',
            'general' => null,
        ];
        $byPillar = $pillarMap[$idea->pillar ?? 'general'] ?? null;
        if ($byPillar !== null) return $byPillar;

        // Niche fallback. Strip the "& Tech" suffix and similar separators.
        $niche = trim((string) ($idea->niche ?? ''));
        if ($niche === '' || strcasecmp($niche, 'AI & Tech') === 0) return 'AI';
        // Take first 1-2 words, drop punctuation
        $first = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $niche);
        $words = preg_split('/\s+/', trim($first));
        return implode(' ', array_slice($words, 0, 2));
    }

    /**
     * Extract candidate SEO keywords from a title string. Returns ordered,
     * deduped list filtered against tokens already in the primary keyword
     * to avoid emitting "gugatan Musk vs Altman OpenAI 2026, Musk, Altman,
     * OpenAI, 2026" which just restates the primary in fragments.
     *
     * Strategy:
     *   1. Multi-word proper-noun bigrams ("Sam Altman", "Elon Musk")
     *   2. Single-word capitalized tokens excluding stopwords + colon-suffix
     *      framing ("Bukan:" or trailing punctuation)
     *   3. ALL-CAPS acronyms (OpenAI is mixed case; SPAC, IPO, AI etc are
     *      all-caps and meaningful as standalone keywords)
     *
     * Idempotent + side-effect free — pure derivation from input string.
     */
    private function extractKeywordTerms(string $source, string $primaryPhrase): array
    {
        if ($source === '') return [];

        // Strip HTML + collapse whitespace for body-text scanning. Then take
        // the lede (first 1200 chars) — that's where news prose introduces
        // named entities. Past the lede the article gets dense and the
        // signal-to-noise drops.
        $text = preg_replace('/\s+/', ' ', strip_tags($source));
        $text = mb_substr((string) $text, 0, 1200);

        // Stopwords: any token that looks like a proper noun by capitalization
        // alone but is actually filler. Sentence starters routinely produce
        // false positives ("The lawsuit", "Itu adalah", "When Musk filed").
        $stop = [
            // EN articles + connectors + common verb-frames
            'The','A','An','And','Or','But','With','Without','For','From','That','When','Just',
            'Will','About','Over','Not','By','To','In','On','At','As','It','Its','Their','This',
            'These','Those','He','She','They','We','You','I','Was','Were','Has','Have','Had',
            'Be','Been','Is','Are','Said','Says','Filed','Wrote','Says','New','Top','How','Why',
            'What','Best','First','Last','Next','Real','Big','Small','Old','Young','More','Most',
            // ID articles + connectors
            'Itu','Ini','Adalah','Yang','Dan','Atau','Dengan','Tanpa','Untuk','Dari','Saat',
            'Saja','Akan','Pada','Oleh','Bukan','Sekadar','Soal','Uang','Telah','Sudah','Lagi',
            'Karena','Tapi','Jika','Kalau','Lalu','Kemudian','Bahwa','Mereka','Dia','Kami','Kita',
            // News-prose fillers
            'Lawsuit','Trial','News','Update','Story','Report','Article','Piece','Way','Time',
            'Year','Month','Day','Today','Yesterday','Tomorrow','Now','Then','Here','There',
        ];

        $candidates = [];

        // Pass 1: TWO-word proper-noun bigrams ("Elon Musk", "Sam Altman",
        // "Dario Amodei"). Both halves must NOT be a stopword. Restricted
        // to bigrams only (3-word chains add noise like "And Then The").
        if (preg_match_all('/\b([A-Z][a-z]{2,})\s+([A-Z][a-z]{2,})\b/u', $text, $m)) {
            foreach ($m[0] as $idx => $full) {
                $left = $m[1][$idx];
                $right = $m[2][$idx];
                if (in_array($left, $stop, true) || in_array($right, $stop, true)) continue;
                $candidates[] = trim($full);
            }
        }

        // Pass 2: brand-style mixed-case tokens (OpenAI, xAI, ChatGPT, iPhone,
        // GitHub) — these are camelcase or have a lowercase prefix followed
        // by a caps run. Generic title-case words won't match.
        if (preg_match_all('/\b([a-z]?[A-Z]{1,2}[a-z]+(?:[A-Z][a-zA-Z]+)+|[a-z][A-Z]+)\b/u', $text, $m)) {
            foreach ($m[1] as $b) {
                $b = trim($b);
                if (mb_strlen($b) < 3) continue;
                $candidates[] = $b;
            }
        }

        // Pass 3: ALL-CAPS acronyms 2-5 letters (AI, IPO, SPAC, NASA, NATO).
        // Lower bound 2 because "AI" is the ubiquitous tech context keyword.
        if (preg_match_all('/\b([A-Z]{2,5})\b/u', $text, $m)) {
            foreach ($m[1] as $a) {
                $a = trim($a);
                if (in_array($a, $stop, true)) continue;
                $candidates[] = $a;
            }
        }

        // Dedupe IDENTICAL strings only — keep entity tokens even when
        // they appear as substrings of the primary phrase. SEO best
        // practice: have the long-tail target keyword AND standalone
        // entity keywords side-by-side, e.g. for primary "gugatan Musk
        // vs Altman OpenAI 2026" we WANT "Musk, Altman, OpenAI" as
        // standalone keywords too — those are what users actually search
        // for ("Sam Altman lawsuit"), and Google ranks each comma-
        // separated term independently.
        //
        // Also dedupe single-word tokens that are themselves substrings
        // of an already-emitted bigram on the same word (avoid
        // "Sam Altman, Altman, Sam" — bigram already covers both halves).
        $seen = [];
        $out = [];
        foreach ($candidates as $c) {
            $lower = mb_strtolower($c);
            if (isset($seen[$lower])) continue;

            // If this is a single word AND we already emitted a bigram
            // containing this word, skip — bigram already ranks for it.
            if (!str_contains($c, ' ')) {
                $coveredByBigram = false;
                foreach ($out as $existing) {
                    if (str_contains($existing, ' ') && stripos($existing, $c) !== false) {
                        $coveredByBigram = true;
                        break;
                    }
                }
                if ($coveredByBigram) continue;
            }

            $seen[$lower] = true;
            $out[] = $c;
        }
        return $out;
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
            // Caption source chain (per CLAUDE.md contract): explicit caption →
            // concept → insert_after_heading. User-authored caption wins because
            // that's the field they actually edit in the admin UI.
            $captionRaw = trim((string) ($img['caption'] ?? ''));
            if ($captionRaw === '') {
                $captionRaw = trim((string) ($img['concept'] ?? ''));
            }
            if ($captionRaw === '') {
                $captionRaw = trim((string) ($img['insert_after_heading'] ?? ''));
            }
            $caption = htmlspecialchars($captionRaw, ENT_QUOTES, 'UTF-8');
            // Alt text prefers concept (semantic description), falls back to caption.
            $altRaw = trim((string) ($img['concept'] ?? '')) ?: $captionRaw;
            $alt = htmlspecialchars($altRaw, ENT_QUOTES, 'UTF-8');
            $figure = '<figure class="my-8 not-prose">'
                . '<img src="' . $url . '" alt="' . $alt . '" class="w-full rounded-xl" loading="lazy" />';
            if ($caption !== '') {
                $figure .= '<figcaption class="text-sm text-neutral-500 dark:text-neutral-400 mt-2 text-center">' . $caption . '</figcaption>';
            }
            $figure .= '</figure>';
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
