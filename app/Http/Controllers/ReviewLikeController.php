<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * レビューへのいいねの追加・取り消しを扱うコントローラー。
 */
class ReviewLikeController extends Controller
{
    /**
     * レビューのいいねを切り替える（未いいねなら追加、いいね済みなら取り消し）。
     *
     * 自分のレビューにもいいねできる。切り替え後は、対象の書籍の詳細画面へリダイレクトする。
     * フラッシュメッセージは付けない。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @param  Review  $review  対象のレビュー（ルートモデルバインディング）
     * @return RedirectResponse 対象の書籍の詳細画面へのリダイレクト
     */
    public function toggle(Request $request, Review $review): RedirectResponse
    {
        $request->user()->likedReviews()->toggle($review->id);

        return redirect()->route('books.show', $review->book_id);
    }
}
