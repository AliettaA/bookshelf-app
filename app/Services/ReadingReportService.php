<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * マイ読書レポート（reports/index）に表示する統計を集計する。
 *
 * 集計はユーザー自身のレビューを1回だけ取得し、あとは Collection メソッドで行う。
 */
class ReadingReportService
{
    /**
     * ユーザーの読書レポート用の統計をまとめて作る。
     *
     * 戻り値の各キーは reports/index が使う形に合わせている。
     * - summary：total_reviews・books_read・average_rating の連想配列
     * - rating_distribution：★1〜★5の件数（キー0〜4の Collection。0件の星も 0 を含む）
     * - top_rated_books：評価4以上の書籍（最大5件）
     * - genre_ratings：ジャンル別の平均評価と件数（最大5件）
     *
     * @param  User  $user  集計対象のユーザー
     * @return array{summary: array{total_reviews: int, books_read: int, average_rating: float}, rating_distribution: Collection<int, int>, top_rated_books: Collection<int, array{id: int, title: string, author: string, rating: int}>, genre_ratings: Collection<int, array{id: int, name: string, count: int, average_rating: float|int}>} 画面に渡す統計
     */
    public function buildStats(User $user): array
    {
        // 書籍とそのジャンルをまとめて読み込み、集計中の N+1 を避ける
        $reviews = $user->reviews()->with('book.genres')->get();

        return [
            'summary' => $this->summary($reviews),
            'rating_distribution' => $this->ratingDistribution($reviews),
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];
    }

    /**
     * 基本統計（総レビュー数・レビューしたユニークな書籍数・平均評価）を作る。
     *
     * 平均評価は丸めない生の値（float）で、レビューが0件なら 0.0 になる。
     *
     * @param  Collection<int, Review>  $reviews  ユーザーのレビュー
     * @return array{total_reviews: int, books_read: int, average_rating: float} 基本統計
     */
    private function summary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => (float) ($reviews->avg('rating') ?? 0),
        ];
    }

    /**
     * ★1〜★5のレビュー件数を作る。
     *
     * 件数が0の星も 0 として含めるため、常に5要素（キー0が★1、キー4が★5）になる。
     *
     * @param  Collection<int, Review>  $reviews  ユーザーのレビュー
     * @return Collection<int, int> ★1〜★5の件数
     */
    private function ratingDistribution(Collection $reviews): Collection
    {
        $countsByRating = $reviews->countBy('rating');

        return collect(range(1, 5))
            ->map(fn (int $rating) => $countsByRating->get($rating, 0));
    }

    /**
     * 評価4以上の書籍を、評価の高い順に最大5件作る。
     *
     * 同じ書籍を複数回レビューしている場合は、その書籍の最高評価のレビューを使う
     * （最高評価が複数あれば新しいレビュー）。書籍同士で評価が同じなら新しいレビューを先にする。
     * 新しさは created_at の降順、同じなら id の降順で決める。
     *
     * @param  Collection<int, Review>  $reviews  ユーザーのレビュー
     * @return Collection<int, array{id: int, title: string, author: string, rating: int}> 高評価の書籍（0始まりの連番）
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        $newestFirst = [['rating', 'desc'], ['created_at', 'desc'], ['id', 'desc']];

        return $reviews
            ->filter(fn (Review $review) => $review->rating >= 4)
            ->groupBy('book_id')
            ->map(fn (Collection $bookReviews) => $bookReviews->sortBy($newestFirst)->first())
            ->sortBy($newestFirst)
            ->take(5)
            ->map(fn (Review $review) => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values();
    }

    /**
     * ジャンルごとの平均評価とレビュー件数を、平均の高い順に最大5件作る。
     *
     * 平均評価が同じなら件数の多い順、それも同じならジャンルIDの小さい順。
     * 1つの書籍に複数のジャンルがある場合は、そのレビューを各ジャンルに数える。
     *
     * @param  Collection<int, Review>  $reviews  ユーザーのレビュー
     * @return Collection<int, array{id: int, name: string, count: int, average_rating: float|int}> ジャンル別の評価（0始まりの連番）
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review) => $review->book->genres->map(fn ($genre) => [
                'genre' => $genre,
                'rating' => $review->rating,
            ]))
            ->groupBy(fn (array $entry) => $entry['genre']->id)
            ->map(fn (Collection $entries) => [
                'id' => $entries->first()['genre']->id,
                'name' => $entries->first()['genre']->name,
                'count' => $entries->count(),
                'average_rating' => $entries->avg('rating'),
            ])
            ->sortBy([['average_rating', 'desc'], ['count', 'desc'], ['id', 'asc']])
            ->take(5)
            ->values();
    }
}
