<?php

use App\Http\Controllers\Api\PosDeviceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS Devices API Routes
|--------------------------------------------------------------------------
*/

Route::get('/outlets/{outlet}/pos-devices', [PosDeviceController::class, 'index'])
    ->middleware(['permission:pos_devices.view', 'outlet.access']);

Route::post('/outlets/{outlet}/pos-devices', [PosDeviceController::class, 'store'])
    ->middleware(['permission:pos_devices.create', 'outlet.access']);

Route::get('/pos-devices/{posDevice}', [PosDeviceController::class, 'show'])
    ->middleware(['permission:pos_devices.view', 'pos_device.access']);

Route::put('/pos-devices/{posDevice}', [PosDeviceController::class, 'update'])
    ->middleware(['permission:pos_devices.update', 'pos_device.access']);

Route::post('/pos-devices/{posDevice}/activate', [PosDeviceController::class, 'activate'])
    ->middleware(['permission:pos_devices.manage', 'pos_device.access']);

Route::post('/pos-devices/{posDevice}/revoke', [PosDeviceController::class, 'revoke'])
    ->middleware(['permission:pos_devices.manage', 'pos_device.access']);

Route::post('/pos-devices/{posDevice}/lock', [PosDeviceController::class, 'lock'])
    ->middleware(['permission:pos_devices.manage', 'pos_device.access']);

Route::post('/pos-devices/{posDevice}/rotate-secret', [PosDeviceController::class, 'rotateSecret'])
    ->middleware(['permission:pos_devices.manage', 'pos_device.access']);
