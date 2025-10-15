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
        'credential_id',
        'credential_url',
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
        return $this->belongsToMany(Gallery::class, 'award_gallery')
                    ->withPivot('sort_order')
                    ->withTimestamps()
                    ->orderBy('sort_order');
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
     * Each gallery has one image
     */
    public function getTotalPhotosAttribute()
    {
        return $this->galleries->count();
    }
}
