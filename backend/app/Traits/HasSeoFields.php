<?php

namespace App\Traits;

trait HasSeoFields
{
    /**
     * Generate SEO meta tags array
     */
    public function getSeoMetaAttribute(): array
    {
        return [
            'title' => $this->meta_title ?? $this->title,
            'description' => $this->meta_description ?? $this->excerpt ?? $this->description,
            'keywords' => $this->meta_keywords ?? $this->getDefaultKeywords(),
            'canonical' => $this->canonical_url ?? $this->getCanonicalUrl(),
            'robots' => $this->index_follow ? 'index,follow' : 'noindex,nofollow',
        ];
    }

    /**
     * Generate Open Graph meta tags
     */
    public function getOgMetaAttribute(): array
    {
        return [
            'title' => $this->og_title ?? $this->meta_title ?? $this->title,
            'description' => $this->og_description ?? $this->meta_description ?? $this->excerpt ?? $this->description,
            'image' => $this->og_image ?? $this->featured_image ?? $this->image ?? asset('images/default-og.jpg'),
            'type' => $this->getOgType(),
            'url' => $this->canonical_url ?? $this->getCanonicalUrl(),
        ];
    }

    /**
     * Generate Schema.org JSON-LD markup
     */
    public function getSchemaMarkupAttribute(): ?array
    {
        if ($this->schema_markup) {
            return is_string($this->schema_markup) 
                ? json_decode($this->schema_markup, true) 
                : $this->schema_markup;
        }

        return $this->generateDefaultSchema();
    }

    /**
     * Get canonical URL
     */
    protected function getCanonicalUrl(): string
    {
        return url($this->getSlugUrl());
    }

    /**
     * Get slug URL based on model
     */
    protected function getSlugUrl(): string
    {
        return match(get_class($this)) {
            'App\Models\Post' => "/blog/{$this->slug}",
            'App\Models\Project' => "/projects/{$this->slug}",
            'App\Models\Category' => "/category/{$this->slug}",
            default => "/{$this->slug}",
        };
    }

    /**
     * Get default keywords from tags or other fields
     */
    protected function getDefaultKeywords(): ?string
    {
        if (isset($this->tags) && $this->tags) {
            $tags = is_string($this->tags) ? json_decode($this->tags, true) : $this->tags;
            return implode(', ', $tags);
        }

        if (isset($this->technologies) && $this->technologies) {
            $tech = is_string($this->technologies) ? json_decode($this->technologies, true) : $this->technologies;
            return implode(', ', $tech);
        }

        return null;
    }

    /**
     * Get Open Graph type
     */
    protected function getOgType(): string
    {
        return match(get_class($this)) {
            'App\Models\Post' => 'article',
            'App\Models\Project' => 'website',
            default => 'website',
        };
    }

    /**
     * Generate default schema markup
     */
    protected function generateDefaultSchema(): ?array
    {
        $modelClass = get_class($this);

        if ($modelClass === 'App\Models\Post') {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $this->title,
                'description' => $this->excerpt ?? $this->meta_description,
                'image' => $this->featured_image ? asset($this->featured_image) : null,
                'datePublished' => $this->published_at?->toIso8601String(),
                'dateModified' => $this->updated_at->toIso8601String(),
                'author' => [
                    '@type' => 'Person',
                    'name' => config('app.author_name', 'Portfolio Author'),
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => config('app.name'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => asset('images/logo.png'),
                    ],
                ],
            ];
        }

        if ($modelClass === 'App\Models\Project') {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'CreativeWork',
                'name' => $this->title,
                'description' => $this->description,
                'image' => $this->image ? asset($this->image) : null,
                'dateCreated' => $this->completed_at?->toIso8601String() ?? $this->created_at->toIso8601String(),
                'author' => [
                    '@type' => 'Person',
                    'name' => config('app.author_name', 'Portfolio Author'),
                ],
            ];
        }

        return null;
    }

    /**
     * Calculate SEO score (simple algorithm)
     */
    public function calculateSeoScore(): int
    {
        $score = 0;

        // Meta title (20 points)
        if ($this->meta_title) {
            $titleLength = strlen($this->meta_title);
            if ($titleLength >= 30 && $titleLength <= 60) {
                $score += 20;
            } elseif ($titleLength > 0) {
                $score += 10;
            }
        }

        // Meta description (20 points)
        if ($this->meta_description) {
            $descLength = strlen($this->meta_description);
            if ($descLength >= 120 && $descLength <= 160) {
                $score += 20;
            } elseif ($descLength > 0) {
                $score += 10;
            }
        }

        // Keywords/Tags (10 points)
        if ($this->meta_keywords || isset($this->tags)) {
            $score += 10;
        }

        // Image (10 points)
        if (isset($this->featured_image) && $this->featured_image) {
            $score += 10;
        } elseif (isset($this->image) && $this->image) {
            $score += 10;
        }

        // Slug quality (10 points)
        if (isset($this->slug) && strlen($this->slug) < 50) {
            $score += 10;
        }

        // Content length (15 points)
        if (isset($this->content)) {
            $contentLength = strlen(strip_tags($this->content));
            if ($contentLength > 1000) {
                $score += 15;
            } elseif ($contentLength > 500) {
                $score += 10;
            } elseif ($contentLength > 200) {
                $score += 5;
            }
        }

        // Schema markup (10 points)
        if ($this->schema_markup) {
            $score += 10;
        }

        // AI Summary for GEO (5 points)
        if (isset($this->ai_summary) && $this->ai_summary) {
            $score += 5;
        }

        return min($score, 100);
    }

    /**
     * Auto-update SEO score before saving
     */
    protected static function bootHasSeoFields()
    {
        static::saving(function ($model) {
            if (isset($model->seo_score)) {
                $model->seo_score = $model->calculateSeoScore();
            }
        });
    }

    /**
     * Generate AI-friendly summary (for GEO)
     */
    public function generateAiSummary(): string
    {
        $content = $this->content ?? $this->description ?? '';
        $text = strip_tags($content);
        
        // Get first 500 characters as summary
        $summary = substr($text, 0, 500);
        
        // Add key information
        $keyInfo = [];
        
        if (isset($this->tags) && $this->tags) {
            $tags = is_string($this->tags) ? json_decode($this->tags, true) : $this->tags;
            $keyInfo[] = "Topics: " . implode(', ', $tags);
        }
        
        if (isset($this->technologies) && $this->technologies) {
            $tech = is_string($this->technologies) ? json_decode($this->technologies, true) : $this->technologies;
            $keyInfo[] = "Technologies: " . implode(', ', $tech);
        }
        
        if (!empty($keyInfo)) {
            $summary .= "\n\n" . implode('. ', $keyInfo) . '.';
        }
        
        return $summary;
    }
}
