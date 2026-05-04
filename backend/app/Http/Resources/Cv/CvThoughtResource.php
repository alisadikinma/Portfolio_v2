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
 */
class CvThoughtResource extends JsonResource
{
    public static $wrap = null;

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
            'excerpt' => $translation?->excerpt,
        ];
    }
}
