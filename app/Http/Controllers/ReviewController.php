<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()
            ->route('books.show', $review->book_id)
            ->with('success', 'レビューを削除しました。');
    }
}
