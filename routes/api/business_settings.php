<?php

use App\Http\Controllers\Api\BusinessSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business Settings API Routes
|--------------------------------------------------------------------------
*/

Route::get('/businesses/{business}/settings', [BusinessSettingController::class, 'show'])
    ->middleware(['permission:businesses.view', 'business.member']);

Route::put('/businesses/{business}/settings', [BusinessSettingController::class, 'update'])
    ->middleware(['permission:businesses.update', 'business.member', 'business.owner']);
