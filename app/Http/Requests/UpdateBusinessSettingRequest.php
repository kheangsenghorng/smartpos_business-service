<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessSettingRequest extends FormRequest
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
            'receipt_prefix' => ['sometimes', 'string', 'max:20'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:100', 'timezone'],
            'tax_enabled' => ['sometimes', 'boolean'],
            'default_tax_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'allow_discount' => ['sometimes', 'boolean'],
            'max_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'auto_lock_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
