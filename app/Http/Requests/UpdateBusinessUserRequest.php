<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'role' => ['nullable', 'string', 'in:owner,manager,cashier,staff,admin'],
            'is_owner' => ['nullable', 'boolean'],
            'pin_code' => ['nullable', 'string', 'digits_between:4,8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
