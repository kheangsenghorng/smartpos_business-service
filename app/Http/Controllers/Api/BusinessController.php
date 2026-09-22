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

        // Platform Admin & Super Admin can view all businesses across the system
        $isAdmin = in_array('admin', $roles, true) || in_array('super_admin', $roles, true);

        if ($isAdmin) {
            $businesses = Business::withCount(['outlets', 'registers', 'posDevices', 'businessUsers'])
                ->get();
        } else {
            $businessIds = BusinessUser::where('user_uuid', $userUuid)
                ->where('status', 'active')
                ->pluck('business_id');

            $businesses = Business::whereIn('id', $businessIds)
                ->where('status', 'active')
                ->withCount(['outlets', 'registers', 'posDevices', 'businessUsers'])
                ->get();
        }

        return response()->json([
            'data' => $businesses,
        ]);
    }

    /**
     * Create a new business and auto-provision default outlet, register, POS device, and email machine credentials.
     */
    public function store(
        StoreBusinessRequest $request,
        \App\Services\BusinessProvisionService $provisioner
    ): JsonResponse {
        $userUuid = $request->input('owner_user_uuid') ?: $request->attributes->get('user_uuid');

        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';

        // Filter out non-business table attributes
        $businessData = array_diff_key($data, array_flip([
            'owner_user_uuid',
            'owner_name',
            'owner_email',
            'owner_phone',
            'owner_role_code',
        ]));

        $business = Business::create($businessData);

        // Automatically make assigned/current user the business owner with designated role
        $ownerRole = $request->input('owner_role_code') ?: 'owner';
        $businessUser = BusinessUser::create([
            'business_id' => $business->id,
            'user_uuid' => $userUuid,
            'role' => $ownerRole,
            'is_owner' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Auto-provision default Outlet, Register, POS Device & email credentials to owner or business email
        $recipientEmail = $request->input('owner_email') ?: $business->email;
        $provisioned = $provisioner->provisionDefaultPosSetup(
            business: $business,
            ownerUser: $businessUser,
            recipientEmail: $recipientEmail
        );

        return response()->json([
            'message' => 'Business created successfully. Default outlet, register, and POS terminal have been provisioned.',
            'data' => $business->fresh(['businessUsers', 'outlets.registers.posDevices', 'settings']),
            'provisioned' => [
                'outlet' => $provisioned['outlet'],
                'register' => $provisioned['register'],
                'pos_device' => $provisioned['pos_device'],
                'credentials' => $provisioned['credentials'],
            ],
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
