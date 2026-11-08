<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trim = fn ($v) => is_string($v) ? trim($v) : $v;

        $this->merge([
            'name' => $trim($this->input('name')),
            'company' => $trim($this->input('company')),
            'email' => $trim($this->input('email')),
            'whatsapp_number' => $trim($this->input('whatsapp_number')),
            'note' => $trim($this->input('note')),
            'source_page' => $trim($this->input('source_page')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/', 'required_if:newsletter_opt_in,true,1'],
            'interest' => ['required', 'in:inhouse,workshop,public_class'],
            'note' => ['nullable', 'string', 'max:1000'],
            'source_page' => ['required', 'string', 'max:120'],
            'locale' => ['required', 'in:id,en'],
            'newsletter_opt_in' => ['nullable', 'boolean'],
            'client_key' => ['required', 'uuid'],
            // Honeypot: handled in the controller (fake success), never a validation error.
            'website' => ['nullable'],
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        // Honeypot hits must look like success, even when other fields are junk.
        if ($this->filled('website')) {
            return;
        }

        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION', 'message' => $validator->errors()->first()],
            ], 422)
        );
    }
}
