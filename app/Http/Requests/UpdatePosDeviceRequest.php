<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePosDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_name' => ['sometimes', 'required', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'platform' => ['nullable', 'string', 'max:50'],
            'outlet_uuid' => ['nullable', 'uuid', 'exists:outlets,uuid'],
            'register_uuid' => ['nullable', 'uuid', 'exists:registers,uuid'],
            'status' => ['nullable', 'string', 'in:pending,active,locked,revoked'],
        ];
    }
}
