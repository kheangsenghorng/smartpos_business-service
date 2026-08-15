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
            'is_owner' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
