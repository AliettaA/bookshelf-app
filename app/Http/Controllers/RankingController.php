<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;

/**
 * 書籍の評価ランキングを扱うコントローラー。
 */
class RankingController extends Controller
{
    /**
     * 評価ランキング画面を表示する。
     *
     * レビューが1件以上ある書籍だけを対象に、平均評価の高い順に並べる。
     * 平均評価が同じ場合はレビュー件数の多い順、それも同じ場合は id の降順にして、上位10件を表示する。
     * ページネーションは行わない。
     *
     * @return View ranking.index ビュー（$rankedBooks: 平均評価 reviews_avg_rating とレビュー件数 reviews_count 付きの書籍）
     */
    public function index(): View
    {
        $rankedBooks = Book::has('reviews')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
