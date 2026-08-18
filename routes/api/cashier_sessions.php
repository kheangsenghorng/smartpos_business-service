<?php

use App\Http\Controllers\Api\CashierSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Sessions API Routes (POS Cashier Login / Switch / Lock)
|--------------------------------------------------------------------------
*/

Route::post('/outlets/{outlet}/cashier-sessions/start', [CashierSessionController::class, 'store'])
    ->middleware(['permission:pos_devices.use', 'outlet.access']);

Route::get('/outlets/{outlet}/cashier-sessions/current', [CashierSessionController::class, 'current'])
    ->middleware(['permission:pos_devices.use', 'outlet.access']);

Route::post('/outlets/{outlet}/cashier-sessions/{cashierSession}/lock', [CashierSessionController::class, 'lock'])
    ->middleware(['permission:pos_devices.use', 'outlet.access']);

Route::post('/outlets/{outlet}/cashier-sessions/{cashierSession}/unlock', [CashierSessionController::class, 'unlock'])
    ->middleware(['permission:pos_devices.use', 'outlet.access']);

Route::post('/outlets/{outlet}/cashier-sessions/{cashierSession}/end', [CashierSessionController::class, 'end'])
    ->middleware(['permission:pos_devices.use', 'outlet.access']);
