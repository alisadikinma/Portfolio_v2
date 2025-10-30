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
    ];

    protected $casts = [
        'received_at' => 'date',
    ];

    protected $appends = ['total_photos'];

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
