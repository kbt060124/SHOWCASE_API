<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ItemOrigin;

class ItemOriginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $origins = ['Marketplace', 'Upload'];
        foreach ($origins as $origin) {
            ItemOrigin::create(['name' => $origin]);
        }
    }
}
