<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessUserRequest;
use App\Http\Requests\UpdateBusinessUserRequest;
use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BusinessUserController extends Controller
{
    /**
     * List all users associated with the specified business.
     */
    public function index(Business $business): JsonResponse
    {
        $users = $business->businessUsers()->get();

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * Add a user to the specified business with role and status.
     */
    public function store(StoreBusinessUserRequest $request, Business $business): JsonResponse
    {
        $data = $request->validated();
        $data['business_id'] = $business->id;
        $data['is_owner'] = $data['is_owner'] ?? false;
        $data['status'] = $data['status'] ?? 'active';
        $data['joined_at'] = now();

        if (! empty($data['pin_code'])) {
            $data['pin_code_hash'] = Hash::make($data['pin_code']);
            unset($data['pin_code']);
        }

        $businessUser = BusinessUser::create($data);

        return response()->json([
            'message' => 'User added to business successfully.',
            'data' => $businessUser,
        ], 201);
    }

    /**
     * Update business user membership details and permissions.
     */
    public function update(UpdateBusinessUserRequest $request, Business $business, BusinessUser $businessUser): JsonResponse
    {
        if ($businessUser->business_id !== $business->id) {
            return response()->json(['message' => 'User does not belong to this business.'], 404);
        }

        $data = $request->validated();

        if ($businessUser->is_owner && (
            (isset($data['is_owner']) && ! $data['is_owner']) ||
            (isset($data['status']) && $data['status'] !== 'active')
        )) {
            $ownersCount = BusinessUser::where('business_id', $business->id)
                ->where('is_owner', true)
                ->where('status', 'active')
                ->count();

            if ($ownersCount <= 1) {
                return response()->json([
                    'message' => 'Cannot demote or suspend the sole owner of the business.',
                ], 422);
            }
        }

        if (isset($data['pin_code'])) {
            if (! empty($data['pin_code'])) {
                $data['pin_code_hash'] = Hash::make($data['pin_code']);
            }
            unset($data['pin_code']);
        }

        $businessUser->update($data);

        return response()->json([
            'message' => 'Business user membership updated successfully.',
            'data' => $businessUser,
        ]);
    }

    /**
     * Suspend a user's membership in the specified business.
     */
    public function suspend(Business $business, BusinessUser $businessUser): JsonResponse
    {
        if ($businessUser->business_id !== $business->id) {
            return response()->json(['message' => 'User does not belong to this business.'], 404);
        }

        if ($businessUser->is_owner) {
            $ownersCount = BusinessUser::where('business_id', $business->id)
                ->where('is_owner', true)
                ->where('status', 'active')
                ->count();

            if ($ownersCount <= 1) {
                return response()->json([
                    'message' => 'Cannot suspend the sole owner of the business.',
                ], 422);
            }
        }

        $businessUser->update(['status' => 'suspended']);

        return response()->json([
            'message' => 'Business user suspended successfully.',
            'data' => $businessUser,
        ]);
    }

    /**
     * Remove a user from the specified business.
     */
    public function destroy(Request $request, Business $business, BusinessUser $businessUser): JsonResponse
    {
        if ($businessUser->business_id !== $business->id) {
            return response()->json(['message' => 'User does not belong to this business.'], 404);
        }

        $currentUserUuid = $request->attributes->get('user_uuid');

        if ($businessUser->is_owner) {
            $ownersCount = BusinessUser::where('business_id', $business->id)
                ->where('is_owner', true)
                ->where('status', 'active')
                ->count();

            if ($ownersCount <= 1) {
                return response()->json([
                    'message' => 'Cannot remove the sole owner of the business.',
                ], 422);
            }
        }

        $businessUser->delete();

        return response()->json([
            'message' => 'Business user removed successfully.',
        ]);
    }
}
