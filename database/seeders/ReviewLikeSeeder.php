<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * 各レビューに0〜3人のユーザーがいいねする。
     *
     * レビューの件数は ReviewSeeder でランダムに決まるため、いいねするユーザーも
     * レビューごとにランダムに選ぶ。自分のレビューにはいいねしない。
     */
    public function run(): void
    {
        $userIds = User::pluck('id');

        Review::all()->each(function (Review $review) use ($userIds) {
            $likerIds = $userIds
                ->reject(fn (int $userId) => $userId === $review->user_id)
                ->random(rand(0, 3))
                ->all();

            $review->likedByUsers()->syncWithoutDetaching($likerIds);
        });
    }
}
