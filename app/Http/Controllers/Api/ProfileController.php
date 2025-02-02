<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function index($user_id)
    {
        $user = User::with('profile')->findOrFail($user_id);

        $rooms = Room::where('user_id', $user_id)
            ->with([
                'liked.profile',
                'comments.user.profile',
                'items'
            ])
            ->get();

        return response()->json([
            'user' => $user,
            'rooms' => $rooms
        ]);
    }

    public function store(Request $request, $userId)
    {
        // リクエストのバリデーション
        $validated = $request->validate([
            'nickname' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'birthday' => 'required|date',
            'gender' => 'required|string|in:male,female,other',
            'attribute' => 'nullable|string|max:255',
            'introduction' => 'nullable|string',
        ]);

        try {
            $user = User::findOrFail($userId);

            $profile = $user->profile()->create($validated);

            return response()->json([
                'message' => 'プロフィールが正常に作成されました',
                'profile' => $profile
            ], 201);
        } catch (\Exception $e) {
            Log::error('プロフィール作成エラー', [
                'error' => $e->getMessage(),
                'userId' => $userId
            ]);

            return response()->json([
                'message' => 'プロフィールの作成に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $user_id)
    {
        try {
            $user = User::with('profile')->findOrFail($user_id);

            // バリデーションルール
            $rules = [
                'nickname' => 'nullable|string',
                'last_name' => 'nullable|string',
                'first_name' => 'nullable|string',
                'attribute' => 'nullable|string',
                'introduction' => 'nullable|string',
                'user_thumbnail' => 'nullable|image|max:5120', // 5MB制限
            ];

            $validated = $request->validate($rules);

            // プロフィールのサムネイルがデフォルト画像の場合は新規作成として扱う
            if ($user->profile->user_thumbnail === 'default_thumbnail.png') {
                if ($request->hasFile('user_thumbnail')) {
                    // サムネイル画像の保存処理
                    $thumbnailFile = $request->file('user_thumbnail');
                    $path = 'user/' . $user_id;
                    $filename = 'thumbnail.' . $thumbnailFile->getClientOriginalExtension();

                    // S3にアップロード
                    $thumbnailFile->storeAs($path, $filename, 's3');
                    $validated['user_thumbnail'] = $filename;
                }

                $user->profile->update($validated);
            } else {
                if ($request->hasFile('user_thumbnail')) {
                    // 古いサムネイル画像を削除（デフォルト画像以外の場合）
                    if ($user->profile->user_thumbnail && $user->profile->user_thumbnail !== 'default_thumbnail.png') {
                        $oldPath = 'user/' . $user_id . '/' . $user->profile->user_thumbnail;
                        Storage::disk('s3')->delete($oldPath);
                    }

                    // 新しいサムネイル画像を保存
                    $thumbnailFile = $request->file('user_thumbnail');
                    $path = 'user/' . $user_id;
                    $filename = 'thumbnail.' . $thumbnailFile->getClientOriginalExtension();

                    // S3にアップロード
                    $thumbnailFile->storeAs($path, $filename, 's3');
                    $validated['user_thumbnail'] = $filename;
                }

                $user->profile->update($validated);
            }

            Log::info('プロフィール更新完了', [
                'user_id' => $user_id,
                'profile' => $user->profile
            ]);

            return response()->json([
                'message' => 'プロフィールを更新しました',
                'user' => $user->load('profile')
            ]);

        } catch (\Exception $e) {
            Log::error('プロフィール更新エラー', [
                'error' => $e->getMessage(),
                'user_id' => $user_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'プロフィールの更新に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $users = User::whereHas('profile', function ($q) use ($query) {
            $q->where('nickname', 'like', '%' . $query . '%');
        })->with('profile')->get();

        return response()->json($users);
    }
}
