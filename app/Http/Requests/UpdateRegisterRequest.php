<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $register = $this->route('register');
        $registerId = is_object($register) ? $register->id : null;
        $outletId = is_object($register) ? $register->outlet_id : null;

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('registers', 'code')->where('outlet_id', $outletId)->ignore($registerId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
