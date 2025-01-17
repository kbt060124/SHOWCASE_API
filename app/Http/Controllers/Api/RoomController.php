<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    public function show($user_id)
    {
        try {
            // user_idに紐づくroomテーブルのデータを取得（関連するitemsも含める）
            $room = Room::with(['items' => function ($query) {
                $query->select('items.*', 'item_room.*')
                    ->join('item_room', 'items.id', '=', 'item_room.item_id');
            }])->where('user_id', $user_id)->get();

            if ($room) {
                return response()->json([
                    'exist_flg' => true,
                    'room' => $room
                ], 200);
            }

            return response()->json([
                'exist_flg' => false
            ], 200);
        } catch (\Exception $e) {
            Log::error('ルーム取得エラー', [
                'error' => $e->getMessage(),
                'user_id' => $user_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'ルーム取得失敗',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        try {
            // 認証ユーザーのIDを取得
            $userId = auth()->id();

            // ユーザーIDに紐づくルームが既に存在するか確認
            $existingRoom = Room::where('user_id', $userId)->first();
            if ($existingRoom) {
                return response()->json([
                    'message' => 'ルームは既に存在します',
                    'room' => $existingRoom
                ], 200);
            }

            // ルーム名を固定値に設定
            $roomName = "サンプルルーム";

            $room = Room::create([
                'user_id' => $userId,
                'name' => $roomName,
            ]);

            return response()->json([
                'message' => 'ルーム作成成功',
                'room' => $room
            ], 201);
        } catch (\Exception $e) {
            Log::error('ルーム作成エラー', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'ルーム作成失敗',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
