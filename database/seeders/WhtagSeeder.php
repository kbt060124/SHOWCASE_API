<?php

namespace Database\Seeders;

use App\Models\Whtag;
use Illuminate\Database\Seeder;

class WhtagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = ['車', 'バイク', '棚', 'ライト'];
        foreach ($tags as $tag) {
            Whtag::create(['name' => $tag]);
        }
    }
}
