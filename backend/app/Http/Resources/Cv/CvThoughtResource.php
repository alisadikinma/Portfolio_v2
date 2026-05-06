<?php

namespace App\Http\Resources\Cv;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CvThoughtResource — Recent published-blog thought-leadership signal for
 * the CV Master Export. Pulls title + excerpt from the primary-language
 * post_translations row (id wins, en falls back, then any locale present).
 *
 * Posts table has NO `title` / `content` columns of its own — those live
 * in post_translations. If a post has no translations at all (legacy
 * artifact), title is null and the consumer can filter/skip the entry.
 *
 * Topics are sourced from the post's category slug + tags array.
 *
 * Excerpt fallback chain (jobhunter audit fix — schema_version 2.0.0+):
 *   1. translation.excerpt — author-curated short summary
 *   2. translation.meta_description — SEO blurb (always populated for
 *      published posts via HasSeoFields)
 *   3. translation.ai_summary — LLM-generated digest (when present)
 *   4. First paragraph of stripped translation.content (240ch cap)
 *
 * Without this fallback, jobhunter consumers get `excerpt: null` for every
 * post that hasn't had its excerpt field manually filled — which loses the
 * topical-relevance preview the cv-tailor + cold-email skills rely on.
 */
class CvThoughtResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Max chars when distilling an excerpt from raw content. 240 fits a
     * tweet-sized preview without bloating the JSON Resume payload.
     */
    protected const CONTENT_EXCERPT_LIMIT = 240;

    public function toArray(Request $request): array
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        // Match the public PostResource preference order (id primary, en
        // fallback, then anything else in the collection).
        $translation = $translations->firstWhere('language', 'id')
            ?? $translations->firstWhere('language', 'en')
            ?? $translations->first();

        $category = $this->relationLoaded('category') ? $this->category : null;

        $topics = collect()
            ->when($category, fn ($c) => $c->push($category->slug ?: $category->name))
            ->merge((array) ($this->tags ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'title' => $translation?->title,
            'url' => $this->slug ? rtrim(config('app.url'), '/') . '/blog/' . $this->slug : null,
            'published_at' => $this->published_at?->format('Y-m-d'),
            'topics' => $topics,
            'excerpt' => $this->resolveExcerpt($translation),
        ];
    }

    /**
     * Cascade through excerpt → meta_description → ai_summary → derived
     * first-paragraph. Returns the first non-empty string in plain text,
     * trimmed to CONTENT_EXCERPT_LIMIT. Returns null only when every
     * source is empty (a post with no body at all — legacy artifact).
     */
    protected function resolveExcerpt(?object $translation): ?string
    {
        if ($translation === null) {
            return null;
        }

        $candidates = [
            $translation->excerpt ?? null,
            $translation->meta_description ?? null,
            $translation->ai_summary ?? null,
            $this->deriveFromContent($translation->content ?? null),
        ];

        foreach ($candidates as $candidate) {
            $clean = $this->normalize($candidate);
            if ($clean !== null && $clean !== '') {
                return $clean;
            }
        }
        return null;
    }

    /**
     * Strip HTML, collapse whitespace, return null when input is null OR
     * empty after cleanup. Single-pass — no markdown rendering, no entity
     * decoding beyond what strip_tags does (sufficient for plain-text
     * preview surface).
     */
    protected function normalize(?string $value): ?string
    {
        if ($value === null) return null;
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($value)));
        return $text === '' ? null : $text;
    }

    /**
     * Pull the first paragraph (or first CONTENT_EXCERPT_LIMIT chars if no
     * paragraph break) out of post body content. Used as the last-resort
     * fallback when no curated excerpt exists.
     */
    protected function deriveFromContent(?string $content): ?string
    {
        if ($content === null || $content === '') {
            return null;
        }

        // Convert paragraph boundaries to newlines so we can split on them
        // BEFORE stripping tags — otherwise "<p>A</p><p>B</p>" collapses to
        // "AB" and we lose the paragraph signal.
        $withBreaks = preg_replace('#</(p|div|li|h[1-6])>#i', "\n\n", $content);
        $withBreaks = preg_replace('#<br\s*/?>#i', "\n", $withBreaks);

        $plain = trim(preg_replace('/[ \t]+/u', ' ', strip_tags($withBreaks)));
        if ($plain === '') {
            return null;
        }

        // Take the first non-empty paragraph; fall back to head-trim if the
        // whole thing is one big block.
        $firstPara = trim(explode("\n\n", $plain)[0]);
        if ($firstPara === '') {
            $firstPara = $plain;
        }

        if (mb_strlen($firstPara, 'UTF-8') <= self::CONTENT_EXCERPT_LIMIT) {
            return $firstPara;
        }

        // Trim on a word boundary so we don't slice mid-word.
        $trimmed = mb_substr($firstPara, 0, self::CONTENT_EXCERPT_LIMIT, 'UTF-8');
        $lastSpace = mb_strrpos($trimmed, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > self::CONTENT_EXCERPT_LIMIT - 40) {
            $trimmed = mb_substr($trimmed, 0, $lastSpace, 'UTF-8');
        }
        return rtrim($trimmed, " ,;:.\t") . '…';
    }
}
