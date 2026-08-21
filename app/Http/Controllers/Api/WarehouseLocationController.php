<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseLocationRequest;
use App\Http\Requests\UpdateWarehouseLocationRequest;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseLocationController extends Controller
{
    /**
     * List all locations for the specified warehouse.
     */
    public function index(Warehouse $warehouse, Request $request): JsonResponse
    {
        $query = $warehouse->locations();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('zone')) {
            $query->where('zone', $request->string('zone'));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Create a new location under the specified warehouse.
     */
    public function store(StoreWarehouseLocationRequest $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validated();
        $data['warehouse_id'] = $warehouse->id;
        $data['status'] = $data['status'] ?? 'active';

        $location = WarehouseLocation::create($data);
        $location->load('warehouse');

        return response()->json([
            'message' => 'Warehouse location created successfully.',
            'data' => $location,
        ], 201);
    }

    /**
     * Display the specified warehouse location details.
     */
    public function show(WarehouseLocation $warehouseLocation): JsonResponse
    {
        $warehouseLocation->load('warehouse');

        return response()->json([
            'data' => $warehouseLocation,
        ]);
    }

    /**
     * Update the specified warehouse location details.
     */
    public function update(UpdateWarehouseLocationRequest $request, WarehouseLocation $warehouseLocation): JsonResponse
    {
        $warehouseLocation->update($request->validated());

        return response()->json([
            'message' => 'Warehouse location updated successfully.',
            'data' => $warehouseLocation->fresh('warehouse'),
        ]);
    }

    /**
     * Delete the specified warehouse location.
     */
    public function destroy(WarehouseLocation $warehouseLocation): JsonResponse
    {
        $warehouseLocation->delete();

        return response()->json([
            'message' => 'Warehouse location deleted successfully.',
        ]);
    }
}
