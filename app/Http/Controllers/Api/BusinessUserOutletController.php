<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignBusinessUserOutletRequest;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessUserOutlet;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;

class BusinessUserOutletController extends Controller
{
    /**
     * List all outlet assignments for the specified business user.
     */
    public function index(Business $business, BusinessUser $businessUser): JsonResponse
    {
        if ($businessUser->business_id !== $business->id) {
            return response()->json(['message' => 'User does not belong to this business.'], 404);
        }

        $assignments = $businessUser->businessUserOutlets()->with('outlet')->get();

        return response()->json([
            'data' => $assignments,
        ]);
    }

    /**
     * Assign an outlet to the specified business user.
     */
    public function store(AssignBusinessUserOutletRequest $request, Business $business, BusinessUser $businessUser): JsonResponse
    {
        if ($businessUser->business_id !== $business->id) {
            return response()->json(['message' => 'User does not belong to this business.'], 404);
        }

        $data = $request->validated();
        $outlet = Outlet::where('uuid', $data['outlet_uuid'])
            ->where('business_id', $business->id)
            ->first();

        if (! $outlet) {
            return response()->json([
                'message' => 'The selected outlet does not belong to this business.',
                'errors' => ['outlet_uuid' => ['The selected outlet is invalid or belongs to another business.']],
            ], 422);
        }

        $assignment = BusinessUserOutlet::updateOrCreate(
            [
                'business_user_id' => $businessUser->id,
                'outlet_id' => $outlet->id,
            ],
            [
                'is_primary' => $data['is_primary'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'assigned_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Outlet assigned to user successfully.',
            'data' => $assignment->load('outlet'),
        ], 201);
    }

    /**
     * Remove an outlet assignment from the specified business user.
     */
    public function destroy(Business $business, BusinessUser $businessUser, Outlet $outlet): JsonResponse
    {
        if ($businessUser->business_id !== $business->id || $outlet->business_id !== $business->id) {
            return response()->json(['message' => 'Resource does not belong to this business.'], 404);
        }

        $assignment = BusinessUserOutlet::where('business_user_id', $businessUser->id)
            ->where('outlet_id', $outlet->id)
            ->first();

        if (! $assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        $assignment->delete();

        return response()->json([
            'message' => 'Outlet assignment removed successfully.',
        ]);
    }
}
