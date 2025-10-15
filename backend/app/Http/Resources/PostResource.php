<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PostResource
 *
 * Transforms Post model data for API responses with i18n support
 */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $language = $request->query('lang') ?? $request->header('Accept-Language', 'en');
        $language = strtolower(substr($language, 0, 2));

        $translation = $this->translations()->where('language', $language)->first();

        if (!$translation) {
            $translation = $this->translations()->where('language', 'en')->first();
        }

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'slug' => $this->slug,
            'featured_image' => $this->featured_image ? url($this->featured_image) : null,
            'tags' => $this->tags ?? [],
            'is_premium' => (bool) $this->is_premium,
            'published' => (bool) $this->published,
            'published_at' => $this->published_at?->toISOString(),
            'views' => $this->views,
            'reading_time' => $this->reading_time,

            // Translated content
            'title' => $translation?->title ?? $this->title,
            'excerpt' => $translation?->excerpt ?? $this->excerpt,
            'content' => $translation?->content ?? $this->content,

            // SEO
            'seo' => [
                'meta_title' => $translation?->meta_title ?? $translation?->title ?? $this->title,
                'meta_description' => $translation?->meta_description ?? $translation?->excerpt ?? $this->excerpt,
                'og_title' => $translation?->og_title,
                'og_description' => $translation?->og_description,
                'canonical_url' => $translation?->canonical_url,
                'ai_summary' => $translation?->ai_summary,
            ],

            // Relationships
            'category' => new CategoryResource($this->whenLoaded('category')),

            // Translation metadata
            'available_translations' => $this->translations->pluck('language')->toArray(),
            'current_language' => $language,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
