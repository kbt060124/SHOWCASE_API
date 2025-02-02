<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ItemRoom;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function index($user_id)
    {
        try {
            // user_idに紐づくroomテーブルのデータを取得
            $rooms = Room::where('user_id', $user_id)->get();

            return response()->json([
                'message' => 'ルーム一覧取得成功',
                'rooms' => $rooms
            ], 200);
        } catch (\Exception $e) {
            Log::error('ルーム一覧取得エラー', [
                'error' => $e->getMessage(),
                'user_id' => $user_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'ルーム一覧取得失敗',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function studio($room_id)
    {
        try {
            $room = Room::with('items')->find($room_id);
            if ($room) {
                return response()->json([
                    'message' => 'ルーム情報取得成功',
                    'room' => $room
                ], 200);
            }

            return response()->json([
                'message' => '指定されたルームが見つかりません',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ルーム取得エラー', [
                'error' => $e->getMessage(),
                'room_id' => $room_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'ルーム取得失敗',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function mainstage($room_id)
    {
        try {
            $room = Room::with([
                'liked.profile',
                'comments.user.profile',
                'items',
                'user.profile'
            ])->find($room_id);
            if ($room) {
                return response()->json([
                    'message' => 'ルーム情報取得成功',
                    'room' => $room
                ], 200);
            }
            return response()->json([
                'message' => '指定されたルームが見つかりません',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ルーム取得エラー', [
                'error' => $e->getMessage(),
                'room_id' => $room_id,
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

    public function update(Request $request, $room_id)
    {
        $request->validate([
            '*.itemId' => 'required|integer',
            '*.position' => 'required|array',
            '*.position.x' => 'required|numeric',
            '*.position.y' => 'required|numeric',
            '*.position.z' => 'required|numeric',
            '*.rotation' => 'required|array',
            '*.rotation.x' => 'required|numeric',
            '*.rotation.y' => 'required|numeric',
            '*.rotation.z' => 'required|numeric',
            '*.rotation.w' => 'required|numeric',
            '*.scaling' => 'required|array',
            '*.scaling.x' => 'required|numeric',
            '*.scaling.y' => 'required|numeric',
            '*.scaling.z' => 'required|numeric',
            '*.parentIndex' => 'required|integer',
        ]);

        try {
            $messages = [];
            $itemRooms = [];

            // リクエストからすべてのitemIdを取得
            $requestItemIds = collect($request->all())->pluck('itemId')->toArray();

            // リクエストに含まれていないitemIdを持つレコードを削除
            ItemRoom::where('room_id', $room_id)
                ->whereNotIn('item_id', $requestItemIds)
                ->delete();

            foreach ($request->all() as $meshData) {
                $itemRoomData = [
                    'position_x' => $meshData['position']['x'],
                    'position_y' => $meshData['position']['y'],
                    'position_z' => $meshData['position']['z'],
                    'rotation_x' => $meshData['rotation']['x'],
                    'rotation_y' => $meshData['rotation']['y'],
                    'rotation_z' => $meshData['rotation']['z'],
                    'rotation_w' => $meshData['rotation']['w'],
                    'scale_x' => $meshData['scaling']['x'],
                    'scale_y' => $meshData['scaling']['y'],
                    'scale_z' => $meshData['scaling']['z'],
                    'parentindex' => $meshData['parentIndex'],
                ];

                $itemRoom = ItemRoom::where('room_id', $room_id)
                    ->where('item_id', $meshData['itemId'])
                    ->first();

                if ($itemRoom) {
                    $itemRoom->update($itemRoomData);
                    $messages[] = 'アイテム位置情報を更新しました';
                } else {
                    $itemRoomData['room_id'] = $room_id;
                    $itemRoomData['item_id'] = $meshData['itemId'];
                    $itemRoom = ItemRoom::create($itemRoomData);
                    $messages[] = 'アイテム位置情報を新規作成しました';
                }

                $itemRooms[] = $itemRoom;
            }

            return response()->json([
                'messages' => $messages,
                'item_rooms' => $itemRooms
            ], 200);
        } catch (\Exception $e) {
            Log::error('Studioの保存エラー', [
                'error' => $e->getMessage(),
                'room_id' => $room_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'Studioの保存に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadThumbnail(Request $request, $room_id)
    {
        try {

            // 認証ユーザーのIDを取得
            $userId = auth()->id();

            // バリデーション
            $request->validate([
                'thumbnail' => 'required|image|max:5120',
            ]);

            $room = Room::findOrFail($room_id);

            if ($request->hasFile('thumbnail')) {
                // 古いサムネイル画像を削除
                $oldPath = 'room/' . $userId . '/' . $room_id . '/thumbnail.png';
                // ファイルの存在確認
                $exists = Storage::disk('s3')->exists($oldPath);

                if ($exists) {
                    Storage::disk('s3')->delete($oldPath);
                    Log::info('古いサムネイル削除完了', ['old_path' => $oldPath]);
                }

                // 新しいサムネイル画像を保存
                $thumbnailFile = $request->file('thumbnail');
                $path = 'room/' . $userId . '/' . $room_id;
                $filename = 'thumbnail.' . $thumbnailFile->getClientOriginalExtension();

                // S3にアップロード
                $thumbnailFile->storeAs($path, $filename, 's3');


                Log::info('ルームサムネイル更新完了', [
                    'room_id' => $room_id,
                    'new_thumbnail' => $filename
                ]);

                return response()->json([
                    'message' => 'サムネイルを更新しました',
                    'room' => $room
                ]);
            }

        } catch (\Exception $e) {
            Log::error('サムネイル更新エラー', [
                'error' => $e->getMessage(),
                'room_id' => $room_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'サムネイルの更新に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
