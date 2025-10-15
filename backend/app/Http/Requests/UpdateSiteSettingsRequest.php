<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'site_logo' => ['nullable', 'image', 'max:5120'], // 5MB max
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],

            // Social media links can be JSON string (from FormData) or array
            'social_media' => ['nullable'],
            'social_media.*.platform' => ['required', 'string', 'max:100'],
            'social_media.*.url' => ['required', 'url', 'max:500'],
            'social_media.*.icon' => ['nullable', 'string', 'max:100'],

            // Meta tags can be JSON string (from FormData) or array
            'meta_tags' => ['nullable'],
            'meta_tags.*.name' => ['required', 'string', 'max:100'],
            'meta_tags.*.content' => ['required', 'string', 'max:500'],

            // Analytics code
            'analytics_code' => ['nullable', 'string'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Decode JSON strings from FormData
        foreach (['social_media', 'meta_tags'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'site_name.max' => 'Site name must not exceed 255 characters.',
            'site_description.max' => 'Site description must not exceed 500 characters.',
            'site_logo.image' => 'The logo must be an image file.',
            'site_logo.max' => 'The logo file size must not exceed 5MB.',
            'contact_email.email' => 'Please provide a valid email address.',
            'contact_phone.max' => 'Phone number must not exceed 50 characters.',

            'social_media.*.platform.required' => 'Platform name is required for each social media link.',
            'social_media.*.url.required' => 'URL is required for each social media link.',
            'social_media.*.url.url' => 'Please provide a valid URL for social media links.',

            'meta_tags.*.name.required' => 'Meta tag name is required.',
            'meta_tags.*.content.required' => 'Meta tag content is required.',
        ];
    }
}
