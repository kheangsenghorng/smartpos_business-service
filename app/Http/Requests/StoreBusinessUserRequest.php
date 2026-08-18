<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessUserRequest extends FormRequest
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
            'user_uuid' => [
                'required',
                'uuid',
                Rule::unique('business_users', 'user_uuid')->where('business_id', $businessId),
            ],
            'outlet_id' => ['nullable', Rule::exists('outlets', 'id')->where('business_id', $businessId)],
            'role' => ['nullable', 'string', 'in:owner,manager,cashier,staff,admin'],
            'is_owner' => ['nullable', 'boolean'],
            'pin_code' => ['nullable', 'string', 'digits_between:4,8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
