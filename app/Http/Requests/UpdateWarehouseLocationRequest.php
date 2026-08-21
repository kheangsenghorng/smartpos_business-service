<?php

namespace App\Http\Requests;

use App\Models\WarehouseLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseLocation = $this->route('warehouseLocation') ?? $this->route('warehouse_location');
        $locationId = is_object($warehouseLocation) ? $warehouseLocation->id : null;
        $warehouseId = is_object($warehouseLocation) ? $warehouseLocation->warehouse_id : null;

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('warehouse_locations', 'code')
                    ->where('warehouse_id', $warehouseId)
                    ->ignore($locationId),
            ],
            'zone' => ['nullable', 'string', 'max:50'],
            'aisle' => ['nullable', 'string', 'max:50'],
            'rack' => ['nullable', 'string', 'max:50'],
            'shelf' => ['nullable', 'string', 'max:50'],
            'bin' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
