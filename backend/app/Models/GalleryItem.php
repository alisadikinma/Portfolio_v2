<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'type',
        'file_path',
        'title',
        'description',
        'sequence',
    ];

    protected $casts = [
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
