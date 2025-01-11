<?php

namespace Database\Seeders;

use App\Models\RoomComment;
use App\Models\User;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomCommentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $rooms = Room::all();

        RoomComment::create([
            'user_id' => $users->random()->id,
            'room_id' => $room->id,
            'comment' => "これはテストコメント{$i}です。",
        ]);
    }
}
