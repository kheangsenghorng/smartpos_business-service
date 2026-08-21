<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('outlet_uuid') && ! $this->has('outlet_id')) {
            $outlet = Outlet::where('uuid', $this->input('outlet_uuid'))->first();
            if ($outlet) {
                $this->merge(['outlet_id' => $outlet->id]);
            }
        }
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
                Rule::unique('warehouses', 'code')->where('business_id', $businessId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'outlet_id' => [
                'nullable',
                'integer',
                Rule::exists('outlets', 'id')->where(function ($query) use ($businessId) {
                    if ($businessId) {
                        $query->where('business_id', $businessId);
                    }
                }),
            ],
            'outlet_uuid' => ['nullable', 'uuid'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
