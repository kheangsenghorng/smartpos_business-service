<?php

use App\Http\Controllers\Api\CashDrawerController;
use App\Http\Controllers\Api\RegisterSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Register Shifts & Cash Drawer Tracking API Routes
|--------------------------------------------------------------------------
*/

// Register Shifts (Opening / Current / History / Closing)
Route::get('/outlets/{outlet}/registers/{register}/shifts', [RegisterSessionController::class, 'index'])
    ->middleware(['permission:registers.view', 'outlet.access', 'register.access']);

Route::get('/outlets/{outlet}/registers/{register}/shifts/current', [RegisterSessionController::class, 'current'])
    ->middleware(['permission:registers.view', 'outlet.access', 'register.access']);

Route::post('/outlets/{outlet}/registers/{register}/shifts/open', [RegisterSessionController::class, 'open'])
    ->middleware(['permission:registers.manage', 'outlet.access', 'register.access']);

Route::post('/outlets/{outlet}/registers/{register}/shifts/{registerSession}/close', [RegisterSessionController::class, 'close'])
    ->middleware(['permission:registers.manage', 'outlet.access', 'register.access']);

// Cash Drawer Session & Movements
Route::get('/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}', [CashDrawerController::class, 'show'])
    ->middleware(['permission:registers.view', 'outlet.access', 'register.access']);

Route::get('/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}/movements', [CashDrawerController::class, 'movements'])
    ->middleware(['permission:registers.view', 'outlet.access', 'register.access']);

Route::post('/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}/movements', [CashDrawerController::class, 'recordMovement'])
    ->middleware(['permission:registers.manage', 'outlet.access', 'register.access']);
