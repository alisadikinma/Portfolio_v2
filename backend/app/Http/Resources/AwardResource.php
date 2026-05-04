<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AwardResource
 *
 * Transforms Award model data for API responses
 */
class AwardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Generate full image URL
        $imageUrl = $this->image 
            ? (str_starts_with($this->image, '/') 
                ? url($this->image) 
                : asset('uploads/awards/' . $this->image)) 
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'award_title' => $this->title, // Alias for frontend
            'description' => $this->description,
            'organization' => $this->organization,
            'issuing_organization' => $this->organization, // Alias for frontend
            'credential_id' => $this->credential_id,
            'credential_url' => $this->credential_url,
            'image' => $imageUrl,
            'thumbnail' => $imageUrl, // Alias for frontend (same as image for now)
            'received_at' => $this->received_at,
            'award_date' => $this->received_at, // Alias for frontend
            'order' => $this->order,
            'is_active' => $this->is_active ?? true,
            'is_featured' => (bool) ($this->is_featured ?? false),
            'featured_gallery_id' => $this->featured_gallery_id,
            'total_photos' => $this->total_photos,
            'galleries' => GalleryResource::collection($this->whenLoaded('galleries')),
            'featured_gallery' => new GalleryResource($this->whenLoaded('featuredGallery')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
