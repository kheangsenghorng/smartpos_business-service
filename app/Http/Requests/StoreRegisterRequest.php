<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $outlet = $this->route('outlet');
        $outletId = is_object($outlet) ? $outlet->id : null;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('registers', 'code')->where('outlet_id', $outletId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
