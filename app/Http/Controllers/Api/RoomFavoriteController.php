<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;

class RoomFavoriteController extends Controller
{
    public function like($roomId)
    {
        $room = Room::findOrFail($roomId);
        $room->liked()->attach(Auth::id());

        return response()->json([
            'message' => 'いいねしました'
        ]);
    }

    public function dislike($roomId)
    {
        $room = Room::findOrFail($roomId);
        $room->liked()->detach(Auth::id());

        return response()->json([
            'message' => 'いいねを解除しました'
        ]);
    }
}
