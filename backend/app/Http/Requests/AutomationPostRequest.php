<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AutomationPostRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:blog_categories,id',
            'slug' => 'nullable|string|max:255|unique:posts,slug,' . ($this->route('id') ?? 'NULL'),
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|string', // Supports URL or base64
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_premium' => 'nullable|boolean',
            'published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Post title is required',
            'title.max' => 'Post title must not exceed 255 characters',
            'content.required' => 'Post content is required',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'slug.unique' => 'This slug is already in use',
            'excerpt.max' => 'Excerpt must not exceed 500 characters',
            'tags.array' => 'Tags must be an array',
            'tags.*.string' => 'Each tag must be a string',
            'tags.*.max' => 'Each tag must not exceed 50 characters',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Convert string boolean values to actual booleans
        $published = $this->input('published');
        if (is_string($published)) {
            $this->merge([
                'published' => filter_var($published, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $isPremium = $this->input('is_premium');
        if (is_string($isPremium)) {
            $this->merge([
                'is_premium' => filter_var($isPremium, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Parse tags if sent as JSON string
        if ($this->has('tags') && is_string($this->input('tags'))) {
            try {
                $tags = json_decode($this->input('tags'), true);
                if (is_array($tags)) {
                    $this->merge(['tags' => $tags]);
                }
            } catch (\Exception $e) {
                // Keep original value if JSON parsing fails
            }
        }
    }
}
