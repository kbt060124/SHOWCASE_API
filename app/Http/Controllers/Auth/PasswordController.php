<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;

class PasswordController extends Controller
{
    public function update(Request $request)
    {
        try {
            $request->validate([
                'current_password' => ['required', 'string'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            $user = Auth::user();

            // 現在のパスワードが正しいか確認
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => '現在のパスワードが正しくありません',
                    'errors' => ['current_password' => ['現在のパスワードが正しくありません']]
                ], 422);
            }

            // パスワードを更新
            $user->password = Hash::make($request->password);
            $user->save();

            Log::info('パスワード更新完了', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'パスワードを更新しました'
            ]);

        } catch (\Exception $e) {
            Log::error('パスワード更新エラー', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'パスワードの更新に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
