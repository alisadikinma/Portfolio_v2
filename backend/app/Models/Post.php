<?php

namespace App\Models;

use App\Traits\HasImageVariants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Post extends Model
{
    use HasFactory, SoftDeletes, HasSlug, HasImageVariants;

    public function imageVariantSource(): string
    {
        return 'featured_image';
    }

    protected $fillable = [
        'image_variants',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'tags',
        'is_premium',
        'published',
        'published_at',
        'views',
        'reading_time',
        // SEO fields (global only)
        'og_image',
        'schema_markup',
        'faq_schema',
        'seo_score',
        'index_follow',
        // Content Engine origin + translation tracking
        'source_idea_id',
        'translation_pending',
        'translation_attempts',
        'last_translation_attempt',
    ];

    protected $casts = [
        'image_variants' => 'array',
        'tags' => 'array',
        'is_premium' => 'boolean',
        'published' => 'boolean',
        'published_at' => 'datetime',
        'schema_markup' => 'array',
        'faq_schema' => 'array',
        'index_follow' => 'boolean',
        'translation_pending' => 'boolean',
        'last_translation_attempt' => 'datetime',
    ];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('slug') // Slug is pre-set by controller, don't auto-generate from title (title lives in translations, not posts table)
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Get the category that owns the post.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope a query to only include published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('published', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }

    /**
     * Scope a query to only include free posts.
     */
    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    /**
     * Scope a query to only include premium posts.
     */
    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    /**
     * Increment the views count.
     */
    public function incrementViews()
    {
        $this->increment('views');
    }

    /**
     * Get the translations for the post.
     */
    public function translations()
    {
        return $this->hasMany(PostTranslation::class);
    }

    /**
     * LinkedIn drafts derived from this blog post. One row per (post, status)
     * generation attempt; regenerate soft-deletes old rows + creates new ones.
     */
    public function linkedinPosts()
    {
        return $this->hasMany(\App\Models\LinkedInPost::class);
    }

    /**
     * Inverse of ContentIdea::post — the Content Engine idea that produced
     * this published post (if any). Manual blog posts return null.
     * Drives virality_score surfacing in the LinkedIn admin queue.
     */
    public function contentIdea()
    {
        return $this->hasOne(\App\Models\ContentIdea::class, 'result_post_id');
    }

    public function relatedPosts()
    {
        return $this->belongsToMany(Post::class, 'related_posts', 'post_id', 'related_post_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Get translation for specific language
     */
    public function translation($language = 'en')
    {
        return $this->translations()->where('language', $language)->first();
    }

    /**
     * Calculate reading time based on content.
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($post) {
            if ($post->content) {
                $wordCount = str_word_count(strip_tags($post->content));
                $post->reading_time = ceil($wordCount / 200); // Average reading speed: 200 words/minute
            }
        });

        // Purge the SSR-enrichment HTML cache (homepage + blog surfaces) whenever
        // a post changes, so crawlers see fresh content before the 1h TTL lapses.
        // See App\Http\Controllers\SpaPrerenderController::purgeForPost().
        $purge = function ($post) {
            \App\Http\Controllers\SpaPrerenderController::purgeForPost($post);
        };
        static::saved($purge);
        static::deleted($purge);
    }
}
