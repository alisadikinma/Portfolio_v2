<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Project extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'content',
        'image',
        'images',
        'category',
        'status',
        'technologies',
        'client',
        'url',
        'github_url',
        'completed_at',
        'start_date',
        'end_date',
        'featured',
        'published',
        'is_active',
        'sort_order',
        // SEO fields
        'meta_title',
        'meta_description',
        'focus_keyword',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
        'schema_markup',
        'ai_summary',
        'tech_stack_details',
        'seo_score',
        'index_follow',
        'tags',
        'meta_keywords',
        // Related projects
        'related_project_ids',
        // Case Study fields (added Nov 3, 2025)
        'domain',
        'impact_statement',
        'context',
        'role',
        'problem',
        'solution',
        'integration',
        'result',
    ];

    protected $casts = [
        'images' => 'array',
        'technologies' => 'array',
        'related_project_ids' => 'array',
        'schema_markup' => 'array',
        'tech_stack_details' => 'array',
        'tags' => 'array',
        'completed_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'featured' => 'boolean',
        'published' => 'boolean',
        'is_active' => 'boolean',
        'index_follow' => 'boolean',
    ];

    /**
     * Get related projects
     */
    public function getRelatedProjects($limit = 3)
    {
        if (!$this->related_project_ids || count($this->related_project_ids) === 0) {
            return collect();
        }

        return self::whereIn('id', $this->related_project_ids)
            ->where('id', '!=', $this->id)
            ->limit($limit)
            ->get();
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Scope a query to only include featured projects.
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get the translations for the project.
     */
    public function translations()
    {
        return $this->hasMany(ProjectTranslation::class);
    }

    /**
     * Get translation for specific language
     */
    public function translation($language = 'en')
    {
        return $this->translations()->where('language', $language)->first();
    }

    /**
     * Accessor for featured_image (alias for image)
     */
    public function getFeaturedImageAttribute()
    {
        return $this->image;
    }

    /**
     * Accessor for client_name (alias for client)
     */
    public function getClientNameAttribute()
    {
        return $this->client;
    }

    /**
     * Accessor for project_url (alias for url)
     */
    public function getProjectUrlAttribute()
    {
        return $this->url;
    }

    /**
     * Accessor for is_featured (alias for featured)
     */
    public function getIsFeaturedAttribute()
    {
        return $this->featured;
    }

    /**
     * Mutator for featured_image (write to image)
     */
    public function setFeaturedImageAttribute($value)
    {
        $this->attributes['image'] = $value;
    }

    /**
     * Mutator for client_name (write to client)
     */
    public function setClientNameAttribute($value)
    {
        $this->attributes['client'] = $value;
    }

    /**
     * Mutator for project_url (write to url)
     */
    public function setProjectUrlAttribute($value)
    {
        $this->attributes['url'] = $value;
    }

    /**
     * Mutator for is_featured (write to featured)
     */
    public function setIsFeaturedAttribute($value)
    {
        $this->attributes['featured'] = (bool) $value;
    }
}
