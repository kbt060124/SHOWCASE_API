<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        foreach ($users as $user) {
            Profile::create([
                'user_id' => $user->id,
                'nickname' => 'ニックネーム' . $user->id,
                'last_name' => '姓' . $user->id,
                'first_name' => '名' . $user->id,
                'user_thumbnail' => 'default_thumbnail.png',
                'main_room_view' => 'default_room.png',
                'main_room_view' => '車'
            ]);
        }
    }
}
