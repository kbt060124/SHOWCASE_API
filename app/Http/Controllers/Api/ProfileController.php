<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Log;

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
        $user = User::with('profile')->findOrFail($user_id);

        if (!$user->profile) {
            $user->profile()->create($request->all());
        } else {
            $user->profile->update($request->all());
        }

        return response()->json($user->load('profile'));
    }
}
