<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

/**
 * レビューの認可ポリシー。
 *
 * レビューの更新・削除は、そのレビューを投稿したユーザー本人だけに許可する。
 */
class ReviewPolicy
{
    /**
     * レビューを更新（編集画面の表示を含む）できるかを判定する。
     *
     * ログインユーザーがレビューの投稿者本人の場合だけ許可する。
     *
     * @param  User  $user  ログインユーザー
     * @param  Review  $review  対象のレビュー
     * @return bool 投稿者本人なら true
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * レビューを削除できるかを判定する。
     *
     * ログインユーザーがレビューの投稿者本人の場合だけ許可する。
     *
     * @param  User  $user  ログインユーザー
     * @param  Review  $review  対象のレビュー
     * @return bool 投稿者本人なら true
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
