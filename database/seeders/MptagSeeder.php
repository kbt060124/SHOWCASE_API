<?php

namespace Database\Seeders;

use App\Models\Mptag;
use Illuminate\Database\Seeder;

class MptagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = ['車', 'バイク', '棚', 'ライト'];
        foreach ($tags as $tag) {
            Mptag::create(['name' => $tag]);
        }
    }
}
