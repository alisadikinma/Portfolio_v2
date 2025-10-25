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
        'company',
        'period',
        'thumbnail',
        'award_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Gallery belongs to an award
     */
    public function award()
    {
        return $this->belongsTo(Award::class);
    }

    /**
     * Gallery has many items (images/videos)
     */
    public function items()
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sequence');
    }

    /**
     * Get total items count
     */
    public function getTotalItemsAttribute()
    {
        return $this->items()->count();
    }
}
