<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreContactRequest
 *
 * Validates contact form submissions
 */
class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Public endpoint - anyone can submit contact form
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
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255', 'min:5'],
            'message' => ['required', 'string', 'max:5000', 'min:10'],
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
            'name.required' => 'Please enter your name',
            'name.min' => 'Name must be at least 2 characters',
            'email.required' => 'Please enter your email address',
            'email.email' => 'Please enter a valid email address',
            'subject.required' => 'Please enter a subject',
            'subject.min' => 'Subject must be at least 5 characters',
            'message.required' => 'Please enter your message',
            'message.min' => 'Message must be at least 10 characters',
            'message.max' => 'Message cannot exceed 5000 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags($this->name),
            'subject' => strip_tags($this->subject),
            'message' => strip_tags($this->message),
        ]);
    }
}
