<?php

namespace Database\Seeders;

use App\Models\Mpcomment;
use App\Models\User;
use App\Models\Marketplace;
use Illuminate\Database\Seeder;

class MpcommentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $marketplaces = Marketplace::all();

        foreach ($marketplaces as $marketplace) {
            for ($i = 1; $i <= 3; $i++) {
                Mpcomment::create([
                    'user_id' => $users->random()->id,
                    'marketplace_id' => $marketplace->id,
                    'comment' => "これはテストコメント{$i}です。",
                ]);
            }
        }
    }
} 
