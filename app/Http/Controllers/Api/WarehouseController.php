<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\Business;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * List all warehouses for the specified business with location counts.
     */
    public function index(Business $business, Request $request): JsonResponse
    {
        $query = $business->warehouses()->withCount('locations')->with('outlet');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->has('outlet_id')) {
            $outletId = $request->input('outlet_id');
            if ($outletId === null || $outletId === 'null') {
                $query->whereNull('outlet_id');
            } else {
                $query->where('outlet_id', $outletId);
            }
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Create a new warehouse under the specified business.
     */
    public function store(StoreWarehouseRequest $request, Business $business): JsonResponse
    {
        $data = $request->validated();
        $data['business_id'] = $business->id;
        $data['status'] = $data['status'] ?? 'active';

        $warehouse = Warehouse::create($data);
        $warehouse->load(['outlet', 'locations']);

        return response()->json([
            'message' => 'Warehouse created successfully.',
            'data' => $warehouse,
        ], 201);
    }

    /**
     * Display the specified warehouse details with outlet and locations.
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load(['business', 'outlet', 'locations']);

        return response()->json([
            'data' => $warehouse,
        ]);
    }

    /**
     * Update the specified warehouse details.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());

        return response()->json([
            'message' => 'Warehouse updated successfully.',
            'data' => $warehouse->fresh(['business', 'outlet', 'locations']),
        ]);
    }

    /**
     * Delete the specified warehouse.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse deleted successfully.',
        ]);
    }
}
