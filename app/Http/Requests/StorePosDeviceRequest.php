<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePosDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => ['required', 'string', 'max:100', 'unique:pos_devices,machine_id'],
            'device_name' => ['required', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'platform' => ['nullable', 'string', 'max:50'],
            'register_uuid' => ['nullable', 'uuid', 'exists:registers,uuid'],
        ];
    }
}
