<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        Room::create([
            'user_id' => $users->random()->id,
            'name' => "サンプルルーム",
        ]);
    }
}
