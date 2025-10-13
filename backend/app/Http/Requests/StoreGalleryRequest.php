<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreGalleryRequest
 *
 * Validates gallery item creation
 */
class StoreGalleryRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'], // 5MB max
            'category' => ['required', 'string', 'max:100'],
            'order' => ['nullable', 'integer', 'min:0'],
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
            'title.required' => 'Gallery title is required',
            'image.required' => 'Please upload an image',
            'image.image' => 'The file must be an image',
            'image.mimes' => 'Image must be: jpeg, jpg, png, gif, or webp',
            'image.max' => 'Image size cannot exceed 5MB',
            'category.required' => 'Gallery category is required',
        ];
    }
}
