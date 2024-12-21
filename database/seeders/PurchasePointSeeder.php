<?php

namespace Database\Seeders;

use App\Models\PurchasePoint;
use App\Models\User;
use Illuminate\Database\Seeder;

class PurchasePointSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        foreach ($users as $user) {
            PurchasePoint::create([
                'user_id' => $user->id,
                'point' => 3000,
            ]);
        }
    }
}
