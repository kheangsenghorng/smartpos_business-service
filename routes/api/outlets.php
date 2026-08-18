<?php

use App\Http\Controllers\Api\OutletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Outlets API Routes
|--------------------------------------------------------------------------
*/

Route::get('/businesses/{business}/outlets', [OutletController::class, 'index'])
    ->middleware(['permission:outlets.view', 'business.member']);

Route::post('/businesses/{business}/outlets', [OutletController::class, 'store'])
    ->middleware(['permission:outlets.create', 'business.member']);

Route::get('/outlets/{outlet}', [OutletController::class, 'show'])
    ->middleware(['permission:outlets.view', 'outlet.access']);

Route::put('/outlets/{outlet}', [OutletController::class, 'update'])
    ->middleware(['permission:outlets.update', 'outlet.access']);

Route::delete('/outlets/{outlet}', [OutletController::class, 'destroy'])
    ->middleware(['permission:outlets.delete', 'outlet.access', 'business.owner']);
