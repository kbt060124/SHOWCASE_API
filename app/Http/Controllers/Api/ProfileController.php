<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\User;

class ProfileController extends Controller
{
    public function show($user_id)
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
}
