<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouse = $this->route('warehouse');
        $warehouseId = is_object($warehouse) ? $warehouse->id : null;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouse_locations', 'code')->where('warehouse_id', $warehouseId),
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
