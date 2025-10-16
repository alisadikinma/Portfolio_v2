<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:menu_items,slug'],
            'url' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'sequence' => ['nullable', 'integer', 'min:0'],
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
            'title.required' => 'Menu item title is required',
            'title.max' => 'Menu item title must not exceed 100 characters',
            'slug.required' => 'Menu item slug is required',
            'slug.unique' => 'This slug is already taken',
            'url.required' => 'Menu item URL is required',
            'url.max' => 'URL must not exceed 255 characters',
            'is_active.boolean' => 'Active status must be true or false',
            'sequence.integer' => 'Sequence must be a number',
            'sequence.min' => 'Sequence must be at least 0',
        ];
    }
}
