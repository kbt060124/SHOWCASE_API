<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Item;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 24; $i++) {
            Item::create([
                'origin_id' => 2,
                'itemtype_id' => 1,
                'filetype_id' => 1,
                'totalsize' => rand(1000, 1000000),
                'filename' => "{$i}.glb",
            ]);
        }
    }
}
