<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * 各書籍にランダムなレビューを登録する。
     *
     * - 件数：書籍ごとに2〜4件（rand(2, 4)）
     * - 投稿者：全ユーザーから件数分を重複なしでランダムに選ぶ（$users->random($reviewCount)）
     * - 評価：1〜5からランダム
     * - コメント：評価に対応する日本語テンプレートからランダム
     */
    public function run(): void
    {
        $users = User::all();

        $commentsByRating = [
            5 => ['素晴らしい本でした！', '人生が変わりました。', '何度も読み返しています。'],
            4 => ['とても参考になりました。', '読みやすくておすすめです。', '期待通りの内容でした。'],
            3 => ['普通でした。', '可もなく不可もなく。', '期待したほどではなかった。'],
            2 => ['少し期待外れでした。', '内容が薄い印象。', 'もう少し深掘りしてほしかった。'],
            1 => ['残念ながら合いませんでした。', '期待と違いました。'],
        ];

        Book::all()->each(function (Book $book) use ($users, $commentsByRating) {
            $reviewCount = rand(2, 4);

            $users->random($reviewCount)->each(function (User $user) use ($book, $commentsByRating) {
                $rating = rand(1, 5);

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => collect($commentsByRating[$rating])->random(),
                ]);
            });
        });
    }
}
