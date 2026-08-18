<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBusinessSettingRequest;
use App\Models\Business;
use App\Models\BusinessSetting;
use Illuminate\Http\JsonResponse;

class BusinessSettingController extends Controller
{
    /**
     * Display POS settings for the specified business.
     */
    public function show(Business $business): JsonResponse
    {
        $settings = $business->settings ?? BusinessSetting::firstOrCreate(
            ['business_id' => $business->id],
            [
                'receipt_prefix' => 'REC',
                'currency_code' => $business->currency_code ?? 'USD',
                'timezone' => $business->timezone ?? 'Asia/Phnom_Penh',
                'tax_enabled' => false,
                'default_tax_percent' => 0.00,
                'allow_negative_stock' => false,
                'allow_discount' => true,
                'auto_lock_minutes' => 15,
            ]
        );

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Update POS settings for the specified business.
     */
    public function update(UpdateBusinessSettingRequest $request, Business $business): JsonResponse
    {
        $data = $request->validated();

        $settings = BusinessSetting::updateOrCreate(
            ['business_id' => $business->id],
            $data
        );

        return response()->json([
            'message' => 'Business POS settings updated successfully.',
            'data' => $settings,
        ]);
    }
}
