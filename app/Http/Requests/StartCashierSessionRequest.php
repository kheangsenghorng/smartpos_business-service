<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartCashierSessionRequest extends FormRequest
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
            'register_uuid' => ['required', 'string', 'uuid', 'exists:registers,uuid'],
            'pos_device_uuid' => ['required', 'string', 'uuid', 'exists:pos_devices,uuid'],
            'user_uuid' => ['required', 'string', 'uuid'],
        ];
    }
}
