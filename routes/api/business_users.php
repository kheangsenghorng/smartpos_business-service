<?php

use App\Http\Controllers\Api\BusinessUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business Users API Routes
|--------------------------------------------------------------------------
*/

Route::get('/businesses/{business}/users', [BusinessUserController::class, 'index'])
    ->middleware(['permission:business_users.view', 'business.member']);

Route::post('/businesses/{business}/users', [BusinessUserController::class, 'store'])
    ->middleware(['permission:business_users.manage', 'business.member', 'business.owner']);

Route::put('/businesses/{business}/users/{businessUser}', [BusinessUserController::class, 'update'])
    ->middleware(['permission:business_users.manage', 'business.member', 'business.owner']);

Route::post('/businesses/{business}/users/{businessUser}/suspend', [BusinessUserController::class, 'suspend'])
    ->middleware(['permission:business_users.manage', 'business.member', 'business.owner']);

Route::delete('/businesses/{business}/users/{businessUser}', [BusinessUserController::class, 'destroy'])
    ->middleware(['permission:business_users.manage', 'business.member', 'business.owner']);
