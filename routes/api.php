<?php

use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/warehouses/user/{user_id}', [WarehouseController::class, 'index'])->name('warehouses.index');
