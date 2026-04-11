<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarouselSlideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'carousel_draft_id' => $this->carousel_draft_id,
            'slide_index' => $this->slide_index,
            'slide_type' => $this->slide_type,
            'source_image_url' => $this->source_image_url,
            'prompt' => $this->prompt,
            'generated_image_url' => $this->generated_image_url,
            'generation_uuid' => $this->generation_uuid,
            'generation_status' => $this->generation_status,
            'wow_score' => $this->wow_score,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
