<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * StoreContactRequest
 *
 * Validates contact form submissions with 3-layer anti-bot protection:
 * 1. Honeypot field (invisible trap)
 * 2. Rate limiting (handled by middleware)
 * 3. Time-based validation (minimum 3 seconds)
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
            'email' => [
                'required', 
                'email:rfc,dns', 
                'max:254',
                'regex:/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/'
            ],
            'whatsapp_number' => [
                'required',
                'string',
                'regex:/^[\d\s\+\-\(\)]+$/', // Allow digits, spaces, +, -, (, )
                function ($attribute, $value, $fail) {
                    // Remove non-digit characters
                    $cleaned = preg_replace('/\D/', '', $value);
                    
                    // Check length (8-15 digits)
                    if (strlen($cleaned) < 8) {
                        $fail('Phone number must be at least 8 digits');
                    } elseif (strlen($cleaned) > 15) {
                        $fail('Phone number cannot exceed 15 digits');
                    }
                    
                    // Validate Indonesian format
                    if (substr($cleaned, 0, 1) === '0') {
                        if (strlen($cleaned) < 10 || strlen($cleaned) > 13) {
                            $fail('Invalid Indonesian phone format (e.g., 08123456789)');
                        }
                    } elseif (substr($cleaned, 0, 2) === '62') {
                        if (strlen($cleaned) < 11 || strlen($cleaned) > 14) {
                            $fail('Invalid Indonesian phone format (e.g., +628123456789)');
                        }
                    }
                }
            ],
            'subject' => ['required', 'string', 'max:255', 'min:3'],
            'message' => ['required', 'string', 'max:5000', 'min:10'],
            
            // Anti-bot: Honeypot field (must be empty)
            'website' => ['nullable', 'max:0'],
            
            // Anti-bot: Form timestamp (minimum 3 seconds)
            'form_timestamp' => ['required', 'integer', function ($attribute, $value, $fail) {
                $currentTime = time();
                $timeDiff = $currentTime - $value;
                
                // Reject if submitted too quickly (<3 seconds)
                if ($timeDiff < 3) {
                    Log::warning('Contact form submitted too quickly (bot detected)', [
                        'ip' => request()->ip(),
                        'time_diff' => $timeDiff,
                    ]);
                    $fail('Please take a moment to review your message before submitting.');
                }
                
                // Reject if timestamp is too old (>1 hour)
                if ($timeDiff > 3600) {
                    $fail('Form expired. Please refresh and try again.');
                }
            }],
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
            'email.regex' => 'Email format is invalid',
            'whatsapp_number.required' => 'Please enter your WhatsApp number',
            'whatsapp_number.regex' => 'WhatsApp number can only contain digits, spaces, and +()-',
            'subject.required' => 'Please enter a subject',
            'subject.min' => 'Subject must be at least 3 characters',
            'message.required' => 'Please enter your message',
            'message.min' => 'Message must be at least 10 characters',
            'message.max' => 'Message cannot exceed 5000 characters',
            'form_timestamp.required' => 'Invalid form submission',
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
            'whatsapp_number' => $this->whatsapp_number ? trim($this->whatsapp_number) : null,
        ]);
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        // Log honeypot trap if triggered
        if ($this->filled('website')) {
            Log::warning('Honeypot trap triggered (bot detected)', [
                'ip' => $this->ip(),
                'user_agent' => $this->userAgent(),
                'honeypot_value' => $this->input('website'),
            ]);
        }

        throw (new ValidationException($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }
}
