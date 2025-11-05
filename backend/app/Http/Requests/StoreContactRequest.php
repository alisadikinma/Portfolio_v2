<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * StoreContactRequest
 *
 * Validates contact form submissions with reCAPTCHA v3 verification
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
            'recaptcha_token' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!$this->verifyRecaptcha($value)) {
                    $fail('reCAPTCHA verification failed. Please try again.');
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
            'recaptcha_token.required' => 'reCAPTCHA verification is required',
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
     * Verify reCAPTCHA v3 token with Google API
     *
     * @param string $token
     * @return bool
     */
    protected function verifyRecaptcha(string $token): bool
    {
        // Get reCAPTCHA secret from env
        $secret = env('RECAPTCHA_SECRET_KEY', '6Lf5xAIsAAAAAJsN4txoM5lX1Tu_95OtHX_MjkWC');
        
        // Skip verification in local/testing if explicitly disabled
        if (app()->environment('local') && env('RECAPTCHA_SKIP_VERIFY', false)) {
            Log::info('reCAPTCHA verification skipped in local environment');
            return true;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $this->ip(),
            ]);

            $result = $response->json();

            if (!$response->successful() || !isset($result['success'])) {
                Log::error('reCAPTCHA API request failed', [
                    'status' => $response->status(),
                    'response' => $result,
                ]);
                return false;
            }

            // Check success
            if (!$result['success']) {
                Log::warning('reCAPTCHA verification failed', [
                    'error_codes' => $result['error-codes'] ?? [],
                ]);
                return false;
            }

            // Check score (v3 returns 0.0 - 1.0, higher is better)
            // Threshold: 0.5 (recommended by Google)
            $score = $result['score'] ?? 0;
            if ($score < 0.5) {
                Log::warning('reCAPTCHA score too low', [
                    'score' => $score,
                    'action' => $result['action'] ?? 'unknown',
                ]);
                return false;
            }

            // Verify action matches
            if (isset($result['action']) && $result['action'] !== 'contact_form') {
                Log::warning('reCAPTCHA action mismatch', [
                    'expected' => 'contact_form',
                    'actual' => $result['action'],
                ]);
            }

            Log::info('reCAPTCHA verification success', [
                'score' => $score,
                'action' => $result['action'] ?? 'unknown',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('reCAPTCHA verification exception: ' . $e->getMessage());
            return false;
        }
    }
}
