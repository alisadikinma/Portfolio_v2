<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryItemResource extends JsonResource
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
            'gallery_id' => $this->gallery_id,
            'type' => $this->type,
            'file_path' => $this->file_path ? asset('storage/' . $this->file_path) : null,
            'title' => $this->title,
            'description' => $this->description,
            'sequence' => $this->sequence,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
