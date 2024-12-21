<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 24; $i++) {
            Warehouse::create([
                'user_id' => 1,
                'name' => "アイテム{$i}",
                'thumbnail' => "{$i}.png",
                'favorite' => 0,
                'memo' => "これはアイテム{$i}のメモです。",
            ]);
        }
    }
}
