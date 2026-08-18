<?php

use App\Http\Controllers\Api\RegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Registers API Routes
|--------------------------------------------------------------------------
*/

Route::get('/outlets/{outlet}/registers', [RegisterController::class, 'index'])
    ->middleware(['permission:registers.view', 'outlet.access']);

Route::post('/outlets/{outlet}/registers', [RegisterController::class, 'store'])
    ->middleware(['permission:registers.create', 'outlet.access']);

Route::get('/registers/{register}', [RegisterController::class, 'show'])
    ->middleware(['permission:registers.view', 'register.access']);

Route::put('/registers/{register}', [RegisterController::class, 'update'])
    ->middleware(['permission:registers.update', 'register.access']);

Route::delete('/registers/{register}', [RegisterController::class, 'destroy'])
    ->middleware(['permission:registers.manage', 'register.access', 'business.owner']);
