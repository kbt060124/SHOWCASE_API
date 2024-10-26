<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ItemFiletype;

class ItemFiletypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filetypes = ['glb', 'gltf', 'obj'];
        foreach ($filetypes as $filetype) {
            ItemFiletype::create(['name' => $filetype]);
        }
    }
}
