<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image',
        'category',
        'order',
    ];

    /**
     * Gallery has many images (assuming you have GalleryImage model)
     */
    public function images()
    {
        return $this->hasMany(GalleryImage::class)->orderBy('order');
    }

    /**
     * Gallery belongs to many awards
     */
    public function awards()
    {
        return $this->belongsToMany(Award::class, 'awards_galleries')
                    ->withPivot('display_order')
                    ->withTimestamps()
                    ->orderBy('display_order');
    }
}
