<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_name',
        'company_name',
        'job_title',
        'testimonial_text',
        'client_photo',
        'star_rating',
        'is_active',
        'sort_order',
        'source',
        'source_url',
        'linkedin_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'star_rating' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'star_rating' => 5,
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Bust the homepage SSR HTML cache whenever a testimonial changes, since the
     * homepage Organization JSON-LD node embeds aggregateRating + review[] derived
     * from active testimonials. See SpaPrerenderController::organizationRatingNode().
     */
    public static function boot()
    {
        parent::boot();

        $purge = function () {
            \App\Http\Controllers\SpaPrerenderController::purgeHome();
        };
        static::saved($purge);
        static::deleted($purge);
    }
}
