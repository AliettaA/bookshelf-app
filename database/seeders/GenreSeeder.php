<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    /**
     * 10種類のジャンルを登録する。
     *
     * 同名のジャンルがある場合は作成しない。
     */
    public function run(): void
    {
        collect([
            '小説',
            'ビジネス',
            '技術書',
            '自己啓発',
            'エッセイ',
            '歴史',
            '科学',
            '芸術',
            '料理',
            '旅行',
        ])->each(fn (string $genreName) => Genre::firstOrCreate(['name' => $genreName]));
    }
}
