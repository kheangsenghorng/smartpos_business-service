<?php

use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WarehouseLocationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Warehouses and Warehouse Locations API Routes
|--------------------------------------------------------------------------
*/

Route::get('/businesses/{business}/warehouses', [WarehouseController::class, 'index'])
    ->middleware(['permission:warehouses.view', 'business.member']);

Route::post('/businesses/{business}/warehouses', [WarehouseController::class, 'store'])
    ->middleware(['permission:warehouses.create', 'business.member']);

Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])
    ->middleware(['permission:warehouses.view', 'warehouse.access']);

Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])
    ->middleware(['permission:warehouses.update', 'warehouse.access']);

Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])
    ->middleware(['permission:warehouses.delete', 'warehouse.access', 'business.owner']);

// Warehouse Locations
Route::get('/warehouses/{warehouse}/locations', [WarehouseLocationController::class, 'index'])
    ->middleware(['permission:warehouses.view', 'warehouse.access']);

Route::post('/warehouses/{warehouse}/locations', [WarehouseLocationController::class, 'store'])
    ->middleware(['permission:warehouses.create', 'warehouse.access']);

Route::get('/warehouse-locations/{warehouseLocation}', [WarehouseLocationController::class, 'show'])
    ->middleware(['permission:warehouses.view', 'warehouse_location.access']);

Route::put('/warehouse-locations/{warehouseLocation}', [WarehouseLocationController::class, 'update'])
    ->middleware(['permission:warehouses.update', 'warehouse_location.access']);

Route::delete('/warehouse-locations/{warehouseLocation}', [WarehouseLocationController::class, 'destroy'])
    ->middleware(['permission:warehouses.delete', 'warehouse_location.access', 'business.owner']);
