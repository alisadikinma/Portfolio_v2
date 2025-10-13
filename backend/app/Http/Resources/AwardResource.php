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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'organization' => $this->organization,
            'image' => $this->image,
            'received_at' => $this->received_at?->toISOString(),
            'order' => $this->order,
            'featured_gallery_id' => $this->featured_gallery_id,
            'total_photos' => $this->total_photos,
            'galleries' => GalleryResource::collection($this->whenLoaded('galleries')),
            'featured_gallery' => new GalleryResource($this->whenLoaded('featuredGallery')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
