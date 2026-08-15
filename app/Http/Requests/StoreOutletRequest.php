<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $business = $this->route('business');
        $businessId = is_object($business) ? $business->id : null;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('outlets', 'code')->where('business_id', $businessId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_main_outlet' => ['nullable', 'boolean'],
            'receipt_header' => ['nullable', 'string'],
            'receipt_footer' => ['nullable', 'string'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
