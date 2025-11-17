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

        // Get translation for the specified language (with null safety)
        $translation = null;
        if ($this->relationLoaded('translations')) {
            $translation = $this->translations()
                ->where('language', $language)
                ->first();

            // Fallback to English if translation not found
            if (!$translation) {
                $translation = $this->translations()
                    ->where('language', 'en')
                    ->first();
            }
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            
            // Images (with alias for frontend compatibility)
            'image' => $this->getImageUrl($this->image),
            'featured_image' => $this->getImageUrl($this->image), // Frontend expects this
            'images' => $this->images ? collect($this->images)->map(fn($img) => $this->getImageUrl($img))->toArray() : [],
            
            'category' => $this->category,
            'status' => $this->status ?? 'completed',
            'technologies' => $this->technologies ?? [],
            
            // Client & URLs (with aliases for frontend compatibility)
            'client' => $this->client,
            'client_name' => $this->client, // Frontend expects this
            'url' => $this->url,
            'project_url' => $this->url, // Frontend expects this
            'github_url' => $this->github_url,
            
            // Dates (with aliases for frontend compatibility)
            'completed_at' => $this->completed_at?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            
            'featured' => (bool) $this->featured,
            'is_featured' => (bool) $this->featured,
            'published' => (bool) $this->published,
            'order' => $this->sort_order ?? 0,

            // Translated content (prioritize translation, fallback to main fields)
            'title' => $translation?->title ?? $this->title,
            'description' => $translation?->description ?? $this->description,
            'content' => $translation?->content ?? $this->content,

            // SEO fields (flat structure for form compatibility)
            'meta_title' => $this->meta_title ?: null,
            'meta_description' => $this->meta_description ?: null,
            'focus_keyword' => $this->focus_keyword ?: null,
            'canonical_url' => $this->canonical_url ?: null,
            'og_title' => $this->og_title ?: null,
            'og_description' => $this->og_description ?: null,
            // FIXED: Always return absolute URL for og_image (fallback to featured_image)
            'og_image' => $this->getAbsoluteOgImage(),
            'seo_score' => $this->seo_score ?? 0,

            // Translation metadata
            'available_translations' => $this->whenLoaded('translations', function() {
                return $this->translations->pluck('language')->toArray();
            }, []),
            'current_language' => $language,

            // Related Projects (return IDs array for form)
            'related_project_ids' => $this->related_project_ids ?? [],
            'related_projects' => $this->getRelatedProjectsForDisplay(),

            // Case Study fields (added Nov 3, 2025)
            'impact_statement' => $this->impact_statement,
            'context' => $this->context,
            'role' => $this->role,
            'problem' => $this->problem,
            'solution' => $this->solution,
            'integration' => $this->integration,
            'result' => $this->result,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }

    /**
     * Get related projects data for display
     */
    private function getRelatedProjectsForDisplay(): array
    {
        if (!$this->related_project_ids || !is_array($this->related_project_ids) || empty($this->related_project_ids)) {
            return [];
        }

        try {
            $projects = \App\Models\Project::whereIn('id', $this->related_project_ids)
                ->select('id', 'title', 'slug', 'image', 'description')
                ->get();

            return $projects->map(function($p) {
                $thumbnailUrl = $this->getImageUrl($p->image);

                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'thumbnail' => $thumbnailUrl,
                    'featured_image' => $thumbnailUrl,
                    'description' => $p->description,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Failed to load related projects: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Helper to get correct image URL
     * Handles both /uploads/... and storage/... paths
     */
    private function getImageUrl($imagePath)
    {
        if (!$imagePath) {
            return null;
        }

        // If starts with /, it's already a full path from public
        if (str_starts_with($imagePath, '/')) {
            return url($imagePath);
        }

        // Otherwise, assume it's in storage
        return asset('storage/' . $imagePath);
    }

    /**
     * Get absolute OG image URL with fallback
     * Priority: og_image (absolute URL) > og_image (relative /storage/...) > featured_image
     */
    private function getAbsoluteOgImage()
    {
        // Priority 1: og_image is already absolute URL
        if ($this->og_image && str_starts_with($this->og_image, 'http')) {
            return $this->og_image;
        }

        // Priority 2: og_image is relative path (e.g., /storage/projects/xxx.png)
        if ($this->og_image && str_starts_with($this->og_image, '/')) {
            $baseUrl = rtrim(config('app.url'), '/');
            return $baseUrl . $this->og_image;
        }

        // Priority 3: og_image without leading slash (rare case)
        if ($this->og_image) {
            return $this->getImageUrl($this->og_image);
        }

        // Priority 4: Fallback to featured_image
        if ($this->image) {
            return $this->getImageUrl($this->image);
        }

        // Last resort: null
        return null;
    }
}
