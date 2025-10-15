<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAboutSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'profile_photo' => ['nullable', 'image', 'max:5120'], // 5MB max

            // Skills can be JSON string (from FormData) or array
            'skills' => ['nullable'],
            'skills.*' => ['string', 'max:255'],

            // Experience can be JSON string (from FormData) or array
            'experience' => ['nullable'],
            'experience.*.title' => ['required', 'string', 'max:255'],
            'experience.*.company' => ['required', 'string', 'max:255'],
            'experience.*.location' => ['nullable', 'string', 'max:255'],
            'experience.*.start_date' => ['required', 'string', 'max:50'],
            'experience.*.end_date' => ['nullable', 'string', 'max:50'],
            'experience.*.description' => ['nullable', 'string'],
            'experience.*.current' => ['nullable', 'boolean'],

            // Education can be JSON string (from FormData) or array
            'education' => ['nullable'],
            'education.*.degree' => ['required', 'string', 'max:255'],
            'education.*.institution' => ['required', 'string', 'max:255'],
            'education.*.location' => ['nullable', 'string', 'max:255'],
            'education.*.start_year' => ['required', 'string', 'max:50'],
            'education.*.end_year' => ['nullable', 'string', 'max:50'],
            'education.*.description' => ['nullable', 'string'],

            // Social links can be JSON string (from FormData) or array
            'social_links' => ['nullable'],
            'social_links.*.platform' => ['required', 'string', 'max:100'],
            'social_links.*.url' => ['required', 'url', 'max:500'],
            'social_links.*.icon' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Decode JSON strings from FormData
        foreach (['skills', 'experience', 'education', 'social_links'] as $field) {
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
            'name.max' => 'Name must not exceed 255 characters',
            'title.max' => 'Title must not exceed 255 characters',
            'profile_photo.image' => 'Profile photo must be an image file',
            'profile_photo.max' => 'Profile photo must not exceed 5MB',

            'skills.array' => 'Skills must be an array',
            'skills.*.string' => 'Each skill must be a string',

            'experience.*.title.required' => 'Job title is required for each experience entry',
            'experience.*.company.required' => 'Company name is required for each experience entry',
            'experience.*.start_date.required' => 'Start date is required for each experience entry',

            'education.*.degree.required' => 'Degree is required for each education entry',
            'education.*.institution.required' => 'Institution is required for each education entry',
            'education.*.start_year.required' => 'Start year is required for each education entry',

            'social_links.*.platform.required' => 'Platform name is required for each social link',
            'social_links.*.url.required' => 'URL is required for each social link',
            'social_links.*.url.url' => 'Please provide a valid URL for social links',
        ];
    }
}
