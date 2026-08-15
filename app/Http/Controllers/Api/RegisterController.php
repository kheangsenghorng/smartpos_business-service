<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegisterRequest;
use App\Http\Requests\UpdateRegisterRequest;
use App\Models\Outlet;
use App\Models\Register;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * List all cash registers for the specified outlet with device counts.
     */
    public function index(Outlet $outlet): JsonResponse
    {
        $registers = $outlet->registers()->withCount('posDevices')->get();

        return response()->json([
            'data' => $registers,
        ]);
    }

    /**
     * Create a new cash register under the specified outlet.
     */
    public function store(StoreRegisterRequest $request, Outlet $outlet): JsonResponse
    {
        $data = $request->validated();
        $data['business_id'] = $outlet->business_id;
        $data['outlet_id'] = $outlet->id;
        $data['status'] = $data['status'] ?? 'active';

        $register = Register::create($data);

        return response()->json([
            'message' => 'Register created successfully.',
            'data' => $register,
        ], 201);
    }

    /**
     * Display the specified cash register details with associated POS devices.
     */
    public function show(Register $register): JsonResponse
    {
        $register->load(['business', 'outlet', 'posDevices']);

        return response()->json([
            'data' => $register,
        ]);
    }

    /**
     * Update the specified cash register details.
     */
    public function update(UpdateRegisterRequest $request, Register $register): JsonResponse
    {
        $register->update($request->validated());

        return response()->json([
            'message' => 'Register updated successfully.',
            'data' => $register->fresh(),
        ]);
    }

    /**
     * Delete the specified cash register.
     */
    public function destroy(Register $register): JsonResponse
    {
        $register->delete();

        return response()->json([
            'message' => 'Register deleted successfully.',
        ]);
    }
}
