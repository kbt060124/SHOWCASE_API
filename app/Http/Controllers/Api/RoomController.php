<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ItemRoom;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    public function update(Request $request, $room_id)
    {
        $request->validate([
            'itemId' => 'required|integer',
            'position' => 'required|array',
            'position.x' => 'required|numeric',
            'position.y' => 'required|numeric',
            'position.z' => 'required|numeric',
            'rotation' => 'required|array',
            'rotation.x' => 'required|numeric',
            'rotation.y' => 'required|numeric',
            'rotation.z' => 'required|numeric',
            'rotation.w' => 'required|numeric',
            'scaling' => 'required|array',
            'scaling.x' => 'required|numeric',
            'scaling.y' => 'required|numeric',
            'scaling.z' => 'required|numeric',
            'parentIndex' => 'required|integer',
        ]);

        try {
            // 更新または新規作成するデータ
            $itemRoomData = [
                'position_x' => $request->position['x'],
                'position_y' => $request->position['y'],
                'position_z' => $request->position['z'],
                'rotation_x' => $request->rotation['x'],
                'rotation_y' => $request->rotation['y'],
                'rotation_z' => $request->rotation['z'],
                'rotation_w' => $request->rotation['w'],
                'scale_x' => $request->scaling['x'],
                'scale_y' => $request->scaling['y'],
                'scale_z' => $request->scaling['z'],
                'parentindex' => $request->parentIndex,
            ];

            // 既存のレコードを検索
            $itemRoom = ItemRoom::where('room_id', $room_id)
                              ->where('item_id', $request->itemId)
                              ->first();

            if ($itemRoom) {
                // 既存レコードの更新
                $itemRoom->update($itemRoomData);
                $message = 'アイテム位置情報を更新しました';
            } else {
                // 新規レコードの作成
                $itemRoomData['room_id'] = $room_id;
                $itemRoomData['item_id'] = $request->itemId;
                $itemRoom = ItemRoom::create($itemRoomData);
                $message = 'アイテム位置情報を新規作成しました';
            }

            return response()->json([
                'message' => $message,
                'item_room' => $itemRoom
            ], 200);

        } catch (\Exception $e) {
            Log::error('Studioの保存エラー', [
                'error' => $e->getMessage(),
                'room_id' => $room_id,
                'item_id' => $request->itemId,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'Studioの保存に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
