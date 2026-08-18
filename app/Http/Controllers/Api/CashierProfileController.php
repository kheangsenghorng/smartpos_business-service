<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCashierProfileRequest;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CashierProfile;
use Illuminate\Http\JsonResponse;

class CashierProfileController extends Controller
{
    /**
     * Display cashier POS profile and permissions for the specified business user.
     */
    public function show(Business $business, BusinessUser $businessUser): JsonResponse
    {
        if ($businessUser->business_id !== $business->id) {
            return response()->json(['message' => 'User does not belong to this business.'], 404);
        }

        $profile = $businessUser->cashierProfile ?? CashierProfile::firstOrCreate(
            ['business_user_id' => $businessUser->id],
            [
                'display_name' => $businessUser->job_title ?? 'Cashier',
                'can_sell' => true,
                'can_refund' => false,
                'can_void' => false,
                'can_discount' => false,
                'max_discount_percent' => 0.00,
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => $profile,
        ]);
    }

    /**
     * Update cashier POS profile permissions for the specified business user.
     */
    public function update(UpdateCashierProfileRequest $request, Business $business, BusinessUser $businessUser): JsonResponse
    {
        if ($businessUser->business_id !== $business->id) {
            return response()->json(['message' => 'User does not belong to this business.'], 404);
        }

        $data = $request->validated();

        $profile = CashierProfile::updateOrCreate(
            ['business_user_id' => $businessUser->id],
            $data
        );

        return response()->json([
            'message' => 'Cashier profile updated successfully.',
            'data' => $profile,
        ]);
    }
}
