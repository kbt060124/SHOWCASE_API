<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            // ItemOriginSeeder::class,
            // ItemTypeSeeder::class,
            // ItemFiletypeSeeder::class,
            // ItemSeeder::class,
            ProfileSeeder::class,
            MptagSeeder::class,
            MarketplaceSeeder::class,
            MarketplaceMptagSeeder::class,
            WhtagSeeder::class,
            WarehouseSeeder::class,
            // RoomSeeder::class,
            MpcommentSeeder::class,
            // WarehouseCommentSeeder::class,
            // RoomCommentSeeder::class,
            PurchasePointSeeder::class,
        ]);
    }
}
