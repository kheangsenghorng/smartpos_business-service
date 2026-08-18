<?php

use App\Http\Controllers\Api\DeviceSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS Device Sessions API Routes
|--------------------------------------------------------------------------
*/

Route::get('/pos-devices/{posDevice}/sessions', [DeviceSessionController::class, 'index'])
    ->middleware(['permission:pos_devices.view', 'pos_device.access']);

Route::post('/pos-devices/{posDevice}/sessions/{deviceSession}/revoke', [DeviceSessionController::class, 'revoke'])
    ->middleware(['permission:pos_devices.manage', 'pos_device.access']);
