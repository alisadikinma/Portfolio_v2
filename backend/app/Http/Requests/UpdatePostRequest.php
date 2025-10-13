<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
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
        $postId = $this->route('id');

        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($postId)],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'is_premium' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],

            // Translations
            'translations' => ['nullable', 'array'],
            'translations.*.id' => ['nullable', 'integer', 'exists:post_translations,id'],
            'translations.*.language' => ['required', 'string', Rule::in(['en', 'id'])],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['required', 'string', 'max:255'],
            'translations.*.excerpt' => ['nullable', 'string'],
            'translations.*.content' => ['required', 'string'],
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
            'category_id.exists' => 'Selected category does not exist',
            'slug.unique' => 'This post slug is already taken',
            'translations.*.language.required' => 'Translation language is required',
            'translations.*.language.in' => 'Translation language must be either en or id',
            'translations.*.title.required' => 'Translation title is required',
            'translations.*.slug.required' => 'Translation slug is required',
            'translations.*.content.required' => 'Translation content is required',
        ];
    }
}
