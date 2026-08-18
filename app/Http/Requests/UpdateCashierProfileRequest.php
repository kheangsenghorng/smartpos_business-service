<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCashierProfileRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:150'],
            'avatar_url' => ['nullable', 'string', 'url', 'max:255'],
            'can_sell' => ['sometimes', 'boolean'],
            'can_refund' => ['sometimes', 'boolean'],
            'can_void' => ['sometimes', 'boolean'],
            'can_discount' => ['sometimes', 'boolean'],
            'max_discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
