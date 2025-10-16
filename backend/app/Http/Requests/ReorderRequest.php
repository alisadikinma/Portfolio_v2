<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
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
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.sequence' => ['required', 'integer', 'min:0'],
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
            'items.required' => 'Items array is required',
            'items.array' => 'Items must be an array',
            'items.*.id.required' => 'Each item must have an ID',
            'items.*.id.integer' => 'Item ID must be a number',
            'items.*.sequence.required' => 'Each item must have a sequence',
            'items.*.sequence.integer' => 'Sequence must be a number',
            'items.*.sequence.min' => 'Sequence must be at least 0',
        ];
    }
}
