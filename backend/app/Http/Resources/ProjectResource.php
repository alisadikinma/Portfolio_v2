<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProjectResource
 *
 * Transforms Project model data for API responses with i18n support
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get language from request or default to 'en'
        $language = $request->query('lang') ?? $request->header('Accept-Language', 'en');
        $language = strtolower(substr($language, 0, 2));

        // Get translation for the specified language
        $translation = $this->translations()
            ->where('language', $language)
            ->first();

        // Fallback to English if translation not found
        if (!$translation) {
            $translation = $this->translations()
                ->where('language', 'en')
                ->first();
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'images' => $this->images ? collect($this->images)->map(fn($img) => asset('storage/' . $img))->toArray() : [],
            'category' => $this->category,
            'technologies' => $this->technologies ?? [],
            'client' => $this->client,
            'url' => $this->url,
            'completed_at' => $this->completed_at?->format('Y-m-d'),
            'featured' => (bool) $this->featured,
            'published' => (bool) $this->published,
            'order' => $this->order,

            // Translated content (prioritize translation, fallback to main fields)
            'title' => $translation?->title ?? $this->title,
            'description' => $translation?->description ?? $this->description,
            'content' => $translation?->content ?? $this->content,

            // SEO meta data
            'seo' => [
                'meta_title' => $translation?->meta_title ?? $translation?->title ?? $this->title,
                'meta_description' => $translation?->meta_description ?? $translation?->description ?? $this->description,
                'og_title' => $translation?->og_title,
                'og_description' => $translation?->og_description,
                'canonical_url' => $translation?->canonical_url,
                'ai_summary' => $translation?->ai_summary,
            ],

            // Translation metadata
            'available_translations' => $this->translations->pluck('language')->toArray(),
            'current_language' => $language,

            // CTA Section
            'cta' => [
                'title' => $this->cta_title,
                'description' => $this->cta_description,
                'button_text' => $this->cta_button_text,
                'phone_number' => $this->cta_phone_number,
                'has_cta' => $this->hasCta(),
            ],

            // Related Projects
            'related_projects' => $this->related_project_ids ? 
                collect($this->getRelatedProjects())->map(fn($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'featured_image' => $p->featured_image ? asset('storage/' . $p->featured_image) : null,
                    'description' => $p->description,
                ])->toArray() : [],

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
