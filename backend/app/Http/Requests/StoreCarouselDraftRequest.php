<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarouselDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'source_url' => 'nullable|url',
            'shortcode' => 'nullable|string|max:255',
            'source_images' => 'nullable|array',
            'source_images.*.url' => 'required|url',
            'source_images.*.mediaType' => 'required|string',
            'source_analysis' => 'nullable|array',
            'creative_direction' => 'nullable|string',
            'status' => 'sometimes|in:scraping,analyzing,generating,draft,approved,scheduled,posted,failed',
            'target_platform' => 'sometimes|string|in:instagram,tiktok,linkedin',
            'hook_category' => 'nullable|string',
            'captions' => 'nullable|array',
            'captions.instagram' => 'nullable|string',
            'captions.tiktok' => 'nullable|array',
            'captions.linkedin' => 'nullable|string',
            'captions.threads' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'rejection_reason' => 'nullable|string',
            'slides' => 'required|array|min:1',
            'slides.*.slide_index' => 'required|integer|min:0',
            'slides.*.slide_type' => 'required|in:hook,foreshadow,body,peak,cta',
            'slides.*.source_image_url' => 'nullable|url',
            'slides.*.prompt' => 'nullable|string',
            'slides.*.generated_image_url' => 'nullable|url',
            'slides.*.generation_uuid' => 'nullable|string',
            'slides.*.generation_status' => 'sometimes|in:pending,processing,completed,failed',
            'slides.*.wow_score' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Carousel title is required',
            'slides.required' => 'At least one slide is required',
            'slides.min' => 'At least one slide is required',
        ];
    }
}
