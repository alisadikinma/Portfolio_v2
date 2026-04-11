<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarouselDraftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'source_url' => $this->source_url,
            'shortcode' => $this->shortcode,
            'source_images' => $this->source_images,
            'source_analysis' => $this->source_analysis,
            'creative_direction' => $this->creative_direction,
            'status' => $this->status,
            'target_platform' => $this->target_platform,
            'hook_category' => $this->hook_category,
            'captions' => $this->captions,
            'scheduled_at' => $this->scheduled_at,
            'posted_at' => $this->posted_at,
            'post_result' => $this->post_result,
            'rejection_reason' => $this->rejection_reason,
            'slides' => CarouselSlideResource::collection($this->whenLoaded('slides')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
