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
            'is_owner' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
