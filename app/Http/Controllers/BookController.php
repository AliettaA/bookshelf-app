<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * 書籍の一覧・詳細・登録・編集・削除を扱うコントローラー（Web 画面）。
 */
class BookController extends Controller
{
    /**
     * 書籍一覧画面を表示する。
     *
     * ジャンルをまとめて読み込み、レビューの平均評価（reviews_avg_rating）を付けて、
     * 新しい順（created_at の降順、同じ場合は id の降順）に10件ずつ表示する。
     *
     * @return View books.index ビュー（$books: ページネーション付きの書籍一覧）
     */
    public function index(): View
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * 書籍の登録フォームを表示する。
     *
     * 選択肢として全ジャンルを id の昇順で渡す。
     *
     * @return View books.create ビュー（$genres: 全ジャンル）
     */
    public function create(): View
    {
        $genres = Genre::orderBy('id')->get();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍を登録する。
     *
     * ログインユーザーを登録者として書籍を作成し、選択されたジャンルを紐づける。
     * 登録後は、登録した書籍の詳細画面へリダイレクトする。
     *
     * @param  StoreBookRequest  $request  バリデーション済みの登録リクエスト
     * @return RedirectResponse 書籍詳細画面へのリダイレクト（flash: success）
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = $request->user()->books()->create($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍の詳細画面を表示する。
     *
     * ジャンル、レビュー（新しい順）、レビューの投稿者、レビューにいいねしたユーザーを読み込む。
     *
     * @param  Book  $book  表示する書籍（ルートモデルバインディング）
     * @return View books.show ビュー（$book）
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }

    /**
     * 書籍の編集フォームを表示する。
     *
     * 書籍の登録者本人だけが表示できる（BookPolicy の update）。
     *
     * @param  Book  $book  編集する書籍（ルートモデルバインディング）
     * @return View books.edit ビュー（$book: ジャンル読み込み済み、$genres: 全ジャンル）
     *
     * @throws AuthorizationException 登録者本人でない場合（403 になる）
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');
        $genres = Genre::orderBy('id')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍を更新する。
     *
     * 書籍の登録者本人だけが更新できる（BookPolicy の update）。
     * 書籍の内容を更新し、ジャンルの紐づけを選択された内容に置き換える。
     *
     * @param  UpdateBookRequest  $request  バリデーション済みの更新リクエスト
     * @param  Book  $book  更新する書籍（ルートモデルバインディング）
     * @return RedirectResponse 書籍詳細画面へのリダイレクト（flash: success）
     *
     * @throws AuthorizationException 登録者本人でない場合（403 になる）
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $book->update($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除する。
     *
     * 書籍の登録者本人だけが削除できる（BookPolicy の delete）。
     * 関連するレビュー・いいね・お気に入り・ジャンルの紐づけは、外部キーの cascadeOnDelete で一緒に削除される。
     *
     * @param  Book  $book  削除する書籍（ルートモデルバインディング）
     * @return RedirectResponse 書籍一覧画面へのリダイレクト（flash: success）
     *
     * @throws AuthorizationException 登録者本人でない場合（403 になる）
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
