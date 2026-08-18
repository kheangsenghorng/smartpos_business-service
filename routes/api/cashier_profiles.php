<?php

use App\Http\Controllers\Api\BusinessUserOutletController;
use App\Http\Controllers\Api\CashierProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Profiles & Staff Outlet Assignments API Routes
|--------------------------------------------------------------------------
*/

// Cashier Profile & Permissions
Route::get('/businesses/{business}/users/{businessUser}/cashier-profile', [CashierProfileController::class, 'show'])
    ->middleware(['permission:business_users.view', 'business.member']);

Route::put('/businesses/{business}/users/{businessUser}/cashier-profile', [CashierProfileController::class, 'update'])
    ->middleware(['permission:business_users.manage', 'business.member', 'business.owner']);

// Staff Outlet Assignments
Route::get('/businesses/{business}/users/{businessUser}/outlets', [BusinessUserOutletController::class, 'index'])
    ->middleware(['permission:business_users.view', 'business.member']);

Route::post('/businesses/{business}/users/{businessUser}/outlets', [BusinessUserOutletController::class, 'store'])
    ->middleware(['permission:business_users.manage', 'business.member', 'business.owner']);

Route::delete('/businesses/{business}/users/{businessUser}/outlets/{outlet}', [BusinessUserOutletController::class, 'destroy'])
    ->middleware(['permission:business_users.manage', 'business.member', 'business.owner']);
