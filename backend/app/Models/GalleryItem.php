<?php

namespace App\Models;

use App\Traits\HasImageVariants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    use HasFactory, HasImageVariants;

    public function imageVariantSource(): string
    {
        return 'file_path';
    }

    protected $fillable = [
        'image_variants',
        'gallery_id',
        'type',
        'file_path',
        'title',
        'description',
        'sequence',
    ];

    protected $casts = [
        'image_variants' => 'array',
        'sequence' => 'integer',
    ];

    /**
     * GalleryItem belongs to a gallery
     */
    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Scope to get only images
     */
    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    /**
     * Scope to get only videos
     */
    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }
}
