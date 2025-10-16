<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $projectId = $this->route('id');

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($projectId)],
            'description' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'image', 'max:5120'], // 5MB
            'technologies' => ['nullable', 'array'],
            'technologies.*' => ['string', 'max:100'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'project_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'status' => ['nullable', 'string', 'in:planning,in_progress,completed'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_featured' => ['nullable', 'boolean'],
            
            // SEO fields
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'canonical_url' => ['nullable', 'url', 'max:255'],

            // CTA fields
            'cta_title' => ['nullable', 'string', 'max:255'],
            'cta_description' => ['nullable', 'string', 'max:1000'],
            'cta_button_text' => ['nullable', 'string', 'max:100'],
            'cta_phone_number' => ['nullable', 'string', 'max:20'],
            'related_project_ids' => ['nullable', 'array'],
            'related_project_ids.*' => ['integer', 'exists:projects,id'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'This project slug is already taken',
            'featured_image.image' => 'Featured image must be an image file',
            'featured_image.max' => 'Featured image must not exceed 5MB',
            'status.in' => 'Invalid project status. Must be one of: planning, in_progress, completed',
            'project_url.url' => 'Project URL must be a valid URL',
            'github_url.url' => 'GitHub URL must be a valid URL',
            'canonical_url.url' => 'Canonical URL must be a valid URL',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'meta_title.max' => 'Meta title must not exceed 60 characters',
            'meta_description.max' => 'Meta description must not exceed 160 characters',
            'cta_title.max' => 'CTA title must not exceed 255 characters',
            'cta_description.max' => 'CTA description must not exceed 1000 characters',
            'cta_button_text.max' => 'CTA button text must not exceed 100 characters',
            'cta_phone_number.max' => 'CTA phone number must not exceed 20 characters',
            'related_project_ids.array' => 'Related project IDs must be an array',
            'related_project_ids.*.exists' => 'One or more selected projects do not exist',
        ];
    }
}
