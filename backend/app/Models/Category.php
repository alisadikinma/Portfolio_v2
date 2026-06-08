<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Category extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'order',
    ];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
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
     * Get the posts for the category.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get published posts count.
     */
    public function getPublishedPostsCountAttribute()
    {
        return $this->posts()->published()->count();
    }

    protected static function boot()
    {
        parent::boot();

        // Purge the SSR-enrichment HTML cache (category page + its posts'
        // breadcrumbs + blog index/home) on a category rename/delete so
        // crawlers see the new name before the 1h TTL lapses.
        // See App\Http\Controllers\SpaPrerenderController::purgeForCategory().
        $purge = function ($category) {
            \App\Http\Controllers\SpaPrerenderController::purgeForCategory($category);
        };
        static::saved($purge);
        static::deleted($purge);
    }
}
