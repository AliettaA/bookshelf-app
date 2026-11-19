<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
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

    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        $review->load('book');

        return view('reviews.edit', compact('review'));
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()
            ->route('books.show', $review->book_id)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * レビューを削除して、対象書籍の詳細へ移動する。
     *
     * レビューへのいいねは外部キーの cascadeOnDelete で一緒に消える。
     * 削除は1つのトランザクションで行う。
     *
     * @param  Review  $review  削除するレビュー
     * @return RedirectResponse 書籍詳細へのリダイレクト（success：レビューを削除しました。）
     *
     * @throws AuthorizationException 投稿者本人でない場合（403）
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        DB::transaction(fn () => $review->delete());

        return redirect()
            ->route('books.show', $review->book_id)
            ->with('success', 'レビューを削除しました。');
    }
}
