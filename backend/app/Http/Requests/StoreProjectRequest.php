<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'description' => ['required', 'string'],
            'featured_image' => ['nullable', 'image', 'max:5120'],
            'technologies' => ['nullable', 'array'],
            'technologies.*' => ['string', 'max:100'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'project_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', 'string', 'in:planning,in_progress,completed'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_featured' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
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
            'title.required' => 'Project title is required',
            'slug.required' => 'Project slug is required',
            'slug.unique' => 'This project slug is already taken',
            'description.required' => 'Project description is required',
            'featured_image.image' => 'Featured image must be an image file',
            'featured_image.max' => 'Featured image must not exceed 5MB',
            'status.required' => 'Project status is required',
            'status.in' => 'Invalid project status. Must be one of: planning, in_progress, completed',
            'project_url.url' => 'Project URL must be a valid URL',
            'github_url.url' => 'GitHub URL must be a valid URL',
            'canonical_url.url' => 'Canonical URL must be a valid URL',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'meta_title.max' => 'Meta title must not exceed 60 characters',
            'meta_description.max' => 'Meta description must not exceed 160 characters',
        ];
    }
}
