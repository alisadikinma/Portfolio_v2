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
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get gallery image as a collection for compatibility
     * (Gallery has only one image stored in 'image' column)
     */
    public function getImagesAttribute()
    {
        return collect([$this->image]);
    }

    /**
     * Gallery belongs to many awards
     */
    public function awards()
    {
        return $this->belongsToMany(Award::class, 'award_gallery')
                    ->withPivot('sort_order')
                    ->withTimestamps()
                    ->orderBy('sort_order');
    }
}
