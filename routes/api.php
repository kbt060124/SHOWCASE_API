<?php

use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
Route::post('/warehouses/store', [WarehouseController::class, 'store'])->name('warehouses.store');
Route::get('/warehouses/download/{warehouse_id}', [WarehouseController::class, 'download'])->name('warehouses.download');
