<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
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
            'company' => $this->company,
            'period' => $this->period,
            'thumbnail' => $this->thumbnail ? asset('storage/' . $this->thumbnail) : null,
            'award_id' => $this->award_id,
            'award' => $this->whenLoaded('award', function () {
                return [
                    'id' => $this->award->id,
                    'title' => $this->award->title,
                ];
            }),
            'items' => $this->whenLoaded('items', function () {
                return GalleryItemResource::collection($this->items);
            }),
            'items_count' => $this->whenCounted('items'),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
