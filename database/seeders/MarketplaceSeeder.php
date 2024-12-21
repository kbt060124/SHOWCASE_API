<?php

namespace Database\Seeders;

use App\Models\Marketplace;
use App\Models\User;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        // 24個のマーケットプレイスアイテムを作成
        for ($i = 1; $i <= 10; $i++) {
            Marketplace::create([
                'user_id' => $users->random()->id,
                'name' => "マーケットアイテム{$i}",
                'thumbnail' => "{$i}.png",
                'views' => rand(0, 1000),
                'price' => rand(100, 10000),
                'status' => rand(0, 1),
                'totalsize' => rand(1000, 1000000),
                'filename' => "{$i}.glb",
            ]);
        }
    }
}
