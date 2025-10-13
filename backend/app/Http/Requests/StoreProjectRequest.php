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
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'category' => ['required', 'string', 'in:web,mobile,ai,iot,automation'],
            'technologies' => ['nullable', 'array'],
            'client' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'completed_at' => ['nullable', 'date'],
            'featured' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],

            // Translations
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.language' => ['required', 'string', 'in:en,id'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.content' => ['nullable', 'string'],
            'translations.*.meta_title' => ['nullable', 'string', 'max:255'],
            'translations.*.meta_description' => ['nullable', 'string', 'max:500'],
            'translations.*.og_title' => ['nullable', 'string', 'max:255'],
            'translations.*.og_description' => ['nullable', 'string', 'max:500'],
            'translations.*.canonical_url' => ['nullable', 'url', 'max:255'],
            'translations.*.ai_summary' => ['nullable', 'string'],
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
            'category.required' => 'Project category is required',
            'category.in' => 'Invalid project category. Must be one of: web, mobile, ai, iot, automation',
            'translations.required' => 'At least one translation is required',
            'translations.min' => 'At least one translation is required',
            'translations.*.language.required' => 'Translation language is required',
            'translations.*.language.in' => 'Translation language must be either en or id',
            'translations.*.title.required' => 'Translation title is required',
            'translations.*.slug.required' => 'Translation slug is required',
        ];
    }
}
