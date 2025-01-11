<?php

use App\Http\Controllers\Api\ItemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/item/user/{user_id}', [ItemController::class, 'index'])->name('item.index');
Route::post('/item/upload', [ItemController::class, 'store'])->name('item.store');