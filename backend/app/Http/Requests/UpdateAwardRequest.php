<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAwardRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'organization' => ['required', 'string', 'max:255'],
            'credential_id' => ['nullable', 'string', 'max:255'],
            'credential_url' => ['nullable', 'url', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120'],
            'received_at' => ['required', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'featured_gallery_id' => ['nullable', 'integer', 'exists:galleries,id'],
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
            'title.required' => 'Award title is required',
            'title.max' => 'Award title must not exceed 255 characters',
            'organization.required' => 'Issuing organization is required',
            'organization.max' => 'Organization name must not exceed 255 characters',
            'credential_url.url' => 'Credential URL must be a valid URL',
            'image.image' => 'Award image must be an image file',
            'image.max' => 'Award image must not exceed 5MB',
            'received_at.required' => 'Award date is required',
            'received_at.date' => 'Award date must be a valid date',
            'order.integer' => 'Order must be a number',
            'order.min' => 'Order must be at least 0',
            'featured_gallery_id.exists' => 'Selected gallery does not exist',
        ];
    }
}
