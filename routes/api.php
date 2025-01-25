<?php

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomCommentController;
use App\Http\Controllers\Api\RoomFavoriteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile/{user_id}', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile/update/{user_id}', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/search', [ProfileController::class, 'search'])->name('profile.search');

    Route::get('/item/{user_id}', [ItemController::class, 'index'])->name('item.index');
    Route::get('/item/show/{item_id}', [ItemController::class, 'show'])->name('item.show');
    Route::post('/item/upload', [ItemController::class, 'store'])->name('item.store');
    Route::delete('/item/destroy/{item_id}', [ItemController::class, 'destroy'])->name('item.destroy');
    Route::put('/item/update/{item_id}', [ItemController::class, 'update'])->name('item.update');

    Route::get('/room/{user_id}', [RoomController::class, 'index'])->name('room.index');
    Route::post('/room/create', [RoomController::class, 'create'])->name('room.create');
    Route::put('/room/update/{room_id}', [RoomController::class, 'update'])->name('room.update');
    Route::get('/room/studio/{room_id}', [RoomController::class, 'studio'])->name('room.studio');
    Route::get('/room/mainstage/{room_id}', [RoomController::class, 'mainstage'])->name('room.mainstage');

    Route::post('/room/comment/store/{room_id}', [RoomCommentController::class, 'store'])->name('room.comment.store');
    Route::delete('/room/comment/destroy/{room_comment_id}', [RoomCommentController::class, 'destroy'])->name('room.comment.destroy');

    Route::post('/room/like/{room_id}', [RoomFavoriteController::class, 'like'])->name('room.like');
    Route::post('/room/dislike/{room_id}', [RoomFavoriteController::class, 'dislike'])->name('room.dislike');
});
