<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * お気に入りの一覧表示と、追加・解除の切り替えを扱うコントローラー。
 */
class FavoriteController extends Controller
{
    /**
     * ログインユーザーのお気に入り一覧画面を表示する。
     *
     * お気に入りに追加した日時の新しい順（favorites.created_at の降順、同じ場合は favorites.id の降順）に
     * 10件ずつ表示する。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @return View favorites.index ビュー（$books: ページネーション付きのお気に入り書籍）
     */
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->orderByDesc('favorites.created_at')
            ->orderByDesc('favorites.id')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * 書籍のお気に入りを切り替える（未追加なら追加、追加済みなら解除）。
     *
     * 切り替え後は、操作した画面（書籍詳細・お気に入り一覧）に戻る。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @param  Book  $book  対象の書籍（ルートモデルバインディング）
     * @return RedirectResponse 直前の画面へのリダイレクト（flash: success に追加／解除のメッセージ）
     */
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $result = $request->user()->favoriteBooks()->toggle($book->id);

        $message = $result['attached'] !== []
            ? 'お気に入りに追加しました。'
            : 'お気に入りを解除しました。';

        return redirect()
            ->back()
            ->with('success', $message);
    }
}
