<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomCommentController extends Controller
{
    public function store(Request $request, $room_id)
    {
        $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $comment = RoomComment::create([
            'user_id' => Auth::id(),
            'room_id' => $room_id,
            'comment' => $request->comment,
        ]);

        $comment = RoomComment::with(['user.profile'])->find($comment->id);

        return response()->json([
            'message' => 'コメントを投稿しました',
            'comment' => $comment
        ], 201);
    }

    public function destroy($room_comment_id)
    {
        $comment = RoomComment::findOrFail($room_comment_id);

        if ($comment->user_id !== Auth::id()) {
            return response()->json(['message' => '権限がありません'], 403);
        }

        $comment->delete();
        return response()->json(['message' => 'コメントを削除しました']);
    }
}
