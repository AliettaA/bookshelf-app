<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * レビューの投稿・編集・削除を扱うコントローラー。
 */
class ReviewController extends Controller
{
    /**
     * 書籍にレビューを投稿する。
     *
     * ログインユーザーを投稿者としてレビューを作成し、対象の書籍の詳細画面へリダイレクトする。
     *
     * @param  StoreReviewRequest  $request  バリデーション済みの投稿リクエスト
     * @param  Book  $book  レビューの対象の書籍（ルートモデルバインディング）
     * @return RedirectResponse 書籍詳細画面へのリダイレクト（flash: success）
     */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        $book->reviews()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * レビューの編集フォームを表示する。
     *
     * レビューの投稿者本人だけが表示できる（ReviewPolicy の update）。
     *
     * @param  Review  $review  編集するレビュー（ルートモデルバインディング）
     * @return View reviews.edit ビュー（$review: 書籍 book 読み込み済み）
     *
     * @throws AuthorizationException 投稿者本人でない場合（403 になる）
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        $review->load('book');

        return view('reviews.edit', compact('review'));
    }

    /**
     * レビューを更新する。
     *
     * レビューの投稿者本人だけが更新できる（ReviewPolicy の update）。
     *
     * @param  UpdateReviewRequest  $request  バリデーション済みの更新リクエスト
     * @param  Review  $review  更新するレビュー（ルートモデルバインディング）
     * @return RedirectResponse 対象の書籍の詳細画面へのリダイレクト（flash: success）
     *
     * @throws AuthorizationException 投稿者本人でない場合（403 になる）
     */
    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()
            ->route('books.show', $review->book_id)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * レビューを削除する。
     *
     * レビューの投稿者本人だけが削除できる（ReviewPolicy の delete）。
     * そのレビューへのいいねは、外部キーの cascadeOnDelete で一緒に削除される。
     *
     * @param  Review  $review  削除するレビュー（ルートモデルバインディング）
     * @return RedirectResponse 対象の書籍の詳細画面へのリダイレクト（flash: success）
     *
     * @throws AuthorizationException 投稿者本人でない場合（403 になる）
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()
            ->route('books.show', $review->book_id)
            ->with('success', 'レビューを削除しました。');
    }
}
