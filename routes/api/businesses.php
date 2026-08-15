<?php

use App\Http\Controllers\Api\BusinessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Businesses API Routes
|--------------------------------------------------------------------------
*/

Route::get('/businesses', [BusinessController::class, 'index'])
    ->middleware('permission:businesses.view');

Route::post('/businesses', [BusinessController::class, 'store'])
    ->middleware('permission:businesses.create');

Route::get('/businesses/{business}', [BusinessController::class, 'show'])
    ->middleware(['permission:businesses.view', 'business.member']);

Route::put('/businesses/{business}', [BusinessController::class, 'update'])
    ->middleware(['permission:businesses.update', 'business.member', 'business.owner']);

Route::delete('/businesses/{business}', [BusinessController::class, 'destroy'])
    ->middleware(['permission:businesses.delete', 'business.member', 'business.owner']);
