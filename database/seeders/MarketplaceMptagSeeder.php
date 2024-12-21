<?php

namespace Database\Seeders;

use App\Models\Marketplace;
use App\Models\Mptag;
use Illuminate\Database\Seeder;

class MarketplaceMptagSeeder extends Seeder
{
    public function run(): void
    {
        // 全てのマーケットプレイスアイテムを取得
        $marketplaces = Marketplace::all();

        // 全てのタグを取得
        $mptagIds = Mptag::pluck('id')->toArray();

        // 各マーケットプレイスアイテムに1-3個のランダムなタグを付与
        foreach ($marketplaces as $marketplace) {
            // 1から3のランダムな数を選択
            $numberOfTags = rand(1, 3);

            // ランダムにタグIDを選択
            $selectedTagIds = array_rand(array_flip($mptagIds), $numberOfTags);

            // 配列でない場合（タグが1つの時）は配列に変換
            if (!is_array($selectedTagIds)) {
                $selectedTagIds = [$selectedTagIds];
            }

            // タグを関連付け
            $marketplace->tags()->attach($selectedTagIds);
        }
    }
} 
