<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * StoreContactRequest
 *
 * Validates contact form submissions with hCaptcha verification
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
            'captcha_token' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!$this->verifyHCaptcha($value)) {
                    $fail('Captcha verification failed. Please try again.');
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
            'captcha_token.required' => 'Please complete the captcha verification',
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
     * Verify hCaptcha token with hCaptcha API
     *
     * @param string $token
     * @return bool
     */
    protected function verifyHCaptcha(string $token): bool
    {
        // Get hCaptcha secret from env (use test secret by default)
        $secret = env('HCAPTCHA_SECRET', '0x0000000000000000000000000000000000000000');
        
        // Skip verification in local/testing if no secret configured
        if (app()->environment('local') && $secret === '0x0000000000000000000000000000000000000000') {
            Log::info('hCaptcha verification skipped in local environment');
            return true;
        }

        try {
            $response = Http::asForm()->post('https://hcaptcha.com/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $this->ip(),
            ]);

            $result = $response->json();

            if (!$response->successful() || !isset($result['success'])) {
                Log::error('hCaptcha API request failed', [
                    'status' => $response->status(),
                    'response' => $result,
                ]);
                return false;
            }

            if (!$result['success']) {
                Log::warning('hCaptcha verification failed', [
                    'error_codes' => $result['error-codes'] ?? [],
                ]);
            }

            return $result['success'] === true;
        } catch (\Exception $e) {
            Log::error('hCaptcha verification exception: ' . $e->getMessage());
            return false;
        }
    }
}
