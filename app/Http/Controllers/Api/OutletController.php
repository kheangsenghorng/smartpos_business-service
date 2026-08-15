<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;

class OutletController extends Controller
{
    /**
     * List all outlets for the specified business with device counts.
     */
    public function index(Business $business): JsonResponse
    {
        $outlets = $business->outlets()->withCount(['registers', 'posDevices'])->get();

        return response()->json([
            'data' => $outlets,
        ]);
    }

    /**
     * Create a new outlet under the specified business.
     */
    public function store(StoreOutletRequest $request, Business $business): JsonResponse
    {
        $data = $request->validated();
        $data['business_id'] = $business->id;
        $data['status'] = $data['status'] ?? 'active';

        $outlet = Outlet::create($data);

        return response()->json([
            'message' => 'Outlet created successfully.',
            'data' => $outlet,
        ], 201);
    }

    /**
     * Display the specified outlet details with registers and POS devices.
     */
    public function show(Outlet $outlet): JsonResponse
    {
        $outlet->load(['business', 'registers', 'posDevices']);

        return response()->json([
            'data' => $outlet,
        ]);
    }

    /**
     * Update the specified outlet details.
     */
    public function update(UpdateOutletRequest $request, Outlet $outlet): JsonResponse
    {
        $outlet->update($request->validated());

        return response()->json([
            'message' => 'Outlet updated successfully.',
            'data' => $outlet->fresh(),
        ]);
    }

    /**
     * Delete the specified outlet.
     */
    public function destroy(Outlet $outlet): JsonResponse
    {
        $outlet->delete();

        return response()->json([
            'message' => 'Outlet deleted successfully.',
        ]);
    }
}
