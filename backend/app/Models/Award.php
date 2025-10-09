<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'organization',
        'image',
        'received_at',
        'order',
        'featured_gallery_id'
    ];

    protected $casts = [
        'received_at' => 'date',
    ];

    protected $appends = ['total_photos'];

    /**
     * Award has many galleries (many-to-many)
     */
    public function galleries()
    {
        return $this->belongsToMany(Gallery::class, 'awards_galleries')
                    ->withPivot('display_order')
                    ->withTimestamps()
                    ->orderBy('display_order');
    }

    /**
     * Featured gallery for award
     */
    public function featuredGallery()
    {
        return $this->belongsTo(Gallery::class, 'featured_gallery_id');
    }

    /**
     * Get total photo count from all linked galleries
     */
    public function getTotalPhotosAttribute()
    {
        return $this->galleries->sum(function($gallery) {
            return $gallery->images->count();
        });
    }
}
