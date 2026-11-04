<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * 配列の並びは ReviewSeeder の投稿順（1〜32件目）に対応し、
     * 値は「いいねしたユーザー」の UserSeeder 上の番号（1〜5）。
     * 自分のレビューにはいいねしない。
     */
    public function run(): void
    {
        $userIds = User::orderBy('id')->pluck('id')->values();
        $reviews = Review::orderBy('id')->get()->values();

        $likerNumbersByReview = [
            [1, 3], [2], [], [2, 4, 5], [1], [3], [2, 3, 5], [1, 4],
            [], [3], [2, 5], [1, 2, 3], [4], [], [1, 5], [2],
            [2, 3, 4], [1, 5], [3], [1, 5], [], [2], [3, 4], [1, 3, 5],
            [2], [4], [1, 2], [2, 4, 5], [3], [1, 5], [], [3, 4],
        ];

        foreach ($reviews as $index => $review) {
            $likerIds = collect($likerNumbersByReview[$index])
                ->map(fn (int $userNumber) => $userIds[$userNumber - 1])
                ->reject(fn (int $userId) => $userId === $review->user_id)
                ->all();

            $review->likedByUsers()->syncWithoutDetaching($likerIds);
        }
    }
}
