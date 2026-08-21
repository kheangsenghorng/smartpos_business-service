<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthenticatePosDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => ['required_without:device_code', 'nullable', 'string'],
            'device_code' => ['required_without:machine_id', 'nullable', 'string'],
            'machine_password' => ['required_without:password', 'nullable', 'string'],
            'password' => ['required_without:machine_password', 'nullable', 'string'],
        ];
    }
}
