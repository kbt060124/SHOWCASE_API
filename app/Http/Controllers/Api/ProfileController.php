<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\User;

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

    public function store(Request $request, $user_id)
    {
        $user = User::findOrFail($user_id);

        if ($user->profile) {
            return response()->json([
                'message' => 'Profile already exists'
            ], 400);
        }

        $profile = $user->profile()->create($request->all());

        return response()->json($user->load('profile'));
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
