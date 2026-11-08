<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Award extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'organization',
        'credential_id',
        'credential_url',
        'image',
        'received_at',
        'sort_order',
        'is_featured',
    ];

    protected $casts = [
        // received_at is stored as string to support flexible formats
        // like "January 2025", "Q1 2025", etc.
        'is_featured' => 'boolean',
    ];

    protected $appends = ['total_photos'];

    /**
     * Public URL slug from the title. Generated once on create (the URL must not
     * change when the title is edited); duplicates get -2, -3, ...
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->startSlugSuffixFrom(2)
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Award has many galleries
     */
    public function galleries()
    {
        return $this->hasMany(Gallery::class)->orderBy('sort_order');
    }

    /**
     * Get total photo count from all gallery items
     */
    public function getTotalPhotosAttribute()
    {
        return $this->galleries()
            ->withCount('items')
            ->get()
            ->sum('items_count');
    }
}
