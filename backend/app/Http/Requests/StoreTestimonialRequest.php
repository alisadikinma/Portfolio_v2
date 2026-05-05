<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestimonialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise empty linkedin_url to null so the `nullable|url` rule
     * accepts admin clearing the field via FormData (which sends '').
     */
    protected function prepareForValidation(): void
    {
        $url = $this->input('linkedin_url');
        if (is_string($url) && trim($url) === '') {
            $this->merge(['linkedin_url' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'testimonial_text' => ['required', 'string'],
            'client_photo' => ['nullable', 'image', 'max:5120'],
            'star_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
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
            'client_name.required' => 'Client name is required',
            'client_name.max' => 'Client name must not exceed 255 characters',
            'testimonial_text.required' => 'Testimonial text is required',
            'client_photo.image' => 'Client photo must be an image file',
            'client_photo.max' => 'Client photo must not exceed 5MB',
            'star_rating.required' => 'Star rating is required',
            'star_rating.min' => 'Star rating must be at least 1',
            'star_rating.max' => 'Star rating must not exceed 5',
            'sort_order.integer' => 'Sort order must be a number',
            'sort_order.min' => 'Sort order must be at least 0',
        ];
    }
}
