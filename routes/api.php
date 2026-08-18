<?php

use App\Http\Controllers\Api\PosDeviceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SmartPOS Business Service API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Health check
    Route::get('/business/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'smartpos-business-service',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // POS Device Authentication (Machine auth) - brute-force protected (5 attempts/min)
    Route::post('/pos-devices/auth', [PosDeviceController::class, 'authenticate'])
        ->middleware('throttle:auth');

    // Authenticated Microservice Routes via JWT & Global API Rate Limiter
    Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {

        require __DIR__.'/api/businesses.php';

        require __DIR__.'/api/business_settings.php';

        require __DIR__.'/api/business_users.php';

        require __DIR__.'/api/cashier_profiles.php';

        require __DIR__.'/api/outlets.php';

        require __DIR__.'/api/registers.php';

        require __DIR__.'/api/pos_devices.php';

        require __DIR__.'/api/device_sessions.php';

        require __DIR__.'/api/cashier_sessions.php';

        require __DIR__.'/api/shifts.php';
    });
});
