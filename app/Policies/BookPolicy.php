<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

/**
 * 書籍の認可ポリシー。
 *
 * 書籍の更新・削除は、その書籍を登録したユーザー本人だけに許可する。
 */
class BookPolicy
{
    /**
     * 書籍を更新（編集画面の表示を含む）できるかを判定する。
     *
     * ログインユーザーが書籍の登録者本人の場合だけ許可する。
     *
     * @param  User  $user  ログインユーザー
     * @param  Book  $book  対象の書籍
     * @return bool 登録者本人なら true
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 書籍を削除できるかを判定する。
     *
     * ログインユーザーが書籍の登録者本人の場合だけ許可する。
     *
     * @param  User  $user  ログインユーザー
     * @param  Book  $book  対象の書籍
     * @return bool 登録者本人なら true
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
