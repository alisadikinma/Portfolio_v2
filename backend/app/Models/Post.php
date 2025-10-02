<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Post extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
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
    ];

    protected $casts = [
        'tags' => 'array',
        'is_premium' => 'boolean',
        'published' => 'boolean',
        'published_at' => 'datetime',
    ];

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
    }
}
