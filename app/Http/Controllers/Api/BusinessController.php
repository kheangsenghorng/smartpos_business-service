<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    /**
     * List all active businesses associated with the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $userUuid = $request->attributes->get('user_uuid');
        $roles = $request->attributes->get('jwt_roles', []);

        // Platform Admin can view all businesses across the system
        if (in_array('admin', $roles, true)) {
            $businesses = Business::withCount(['outlets', 'registers', 'posDevices', 'businessUsers'])
                ->get();
        } else {
            $businessIds = BusinessUser::where('user_uuid', $userUuid)
                ->where('status', 'active')
                ->pluck('business_id');

            $businesses = Business::whereIn('id', $businessIds)
                ->withCount(['outlets', 'registers', 'posDevices', 'businessUsers'])
                ->get();
        }

        return response()->json([
            'data' => $businesses,
        ]);
    }

    /**
     * Create a new business and assign current user as the owner.
     */
    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $userUuid = $request->attributes->get('user_uuid');

        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';

        $business = Business::create($data);

        // Automatically make current user the business owner
        BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'is_owner' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Business created successfully.',
            'data' => $business->fresh(['businessUsers', 'outlets']),
        ], 201);
    }

    /**
     * Display the specified business with outlets, registers, and POS devices.
     */
    public function show(Business $business): JsonResponse
    {
        $business->load(['outlets', 'registers', 'posDevices']);
        $business->loadCount('businessUsers');

        return response()->json([
            'data' => $business,
        ]);
    }

    /**
     * Update the specified business details.
     */
    public function update(UpdateBusinessRequest $request, Business $business): JsonResponse
    {
        $business->update($request->validated());

        return response()->json([
            'message' => 'Business updated successfully.',
            'data' => $business->fresh(),
        ]);
    }

    /**
     * Delete the specified business.
     */
    public function destroy(Business $business): JsonResponse
    {
        $business->delete();

        return response()->json([
            'message' => 'Business deleted successfully.',
        ]);
    }
}
