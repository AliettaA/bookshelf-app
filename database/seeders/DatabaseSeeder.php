<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * アプリケーションのデータベースに初期データを投入する。
     *
     * 実行順は依存関係に合わせている（ユーザー → ジャンル → 書籍 → レビュー → お気に入り → いいね → 読書計画）。
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            GenreSeeder::class,
            BookSeeder::class,
            ReviewSeeder::class,
            FavoriteSeeder::class,
            ReviewLikeSeeder::class,
            ReadingPlanSeeder::class,
        ]);
    }
}
