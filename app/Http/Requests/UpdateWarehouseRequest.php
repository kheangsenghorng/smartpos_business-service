<?php

namespace App\Http\Requests;

use App\Models\Outlet;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
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
        $warehouse = $this->route('warehouse');
        $warehouseId = is_object($warehouse) ? $warehouse->id : null;
        $businessId = is_object($warehouse) ? $warehouse->business_id : null;

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')
                    ->where('business_id', $businessId)
                    ->ignore($warehouseId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
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
