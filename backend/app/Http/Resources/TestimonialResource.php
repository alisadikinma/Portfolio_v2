<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TestimonialResource
 *
 * Transforms Testimonial model data for API responses
 */
class TestimonialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_name' => $this->client_name,
            'company_name' => $this->company_name,
            'job_title' => $this->job_title,
            'testimonial_text' => $this->testimonial_text,
            'client_photo' => $this->client_photo,
            'star_rating' => $this->star_rating,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
