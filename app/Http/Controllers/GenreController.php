<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * ジャンルの一覧・詳細・登録・編集・削除を扱うコントローラー。
 *
 * Policy は使わず、ログインユーザーなら誰でも操作できる。
 */
class GenreController extends Controller
{
    /**
     * ジャンル一覧画面を表示する。
     *
     * 書籍数（books_count）を付けて、登録順（id の昇順）に全件表示する。ページネーションは行わない。
     *
     * @return View genres.index ビュー（$genres: 書籍数付きの全ジャンル）
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')
            ->orderBy('id')
            ->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * ジャンルの登録フォームを表示する。
     *
     * @return View genres.create ビュー
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルを登録する。
     *
     * @param  StoreGenreRequest  $request  バリデーション済みの登録リクエスト
     * @return RedirectResponse ジャンル一覧画面へのリダイレクト（flash: success）
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを作成しました。');
    }

    /**
     * ジャンルの詳細画面を表示する。
     *
     * そのジャンルに紐づく書籍を、ジャンルをまとめて読み込んだうえで、
     * 新しい順（created_at の降順、同じ場合は id の降順）に10件ずつ表示する。
     *
     * @param  Genre  $genre  表示するジャンル（ルートモデルバインディング）
     * @return View genres.show ビュー（$genre、$books: ページネーション付きの書籍）
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()
            ->with('genres')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * ジャンルの編集フォームを表示する。
     *
     * @param  Genre  $genre  編集するジャンル（ルートモデルバインディング）
     * @return View genres.edit ビュー（$genre）
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * ジャンルを更新する。
     *
     * @param  UpdateGenreRequest  $request  バリデーション済みの更新リクエスト
     * @param  Genre  $genre  更新するジャンル（ルートモデルバインディング）
     * @return RedirectResponse ジャンル一覧画面へのリダイレクト（flash: success）
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを更新しました。');
    }

    /**
     * ジャンルを削除する。
     *
     * 書籍が紐づいているジャンルは削除せず、エラーメッセージを付けてジャンル一覧へ戻る。
     *
     * @param  Genre  $genre  削除するジャンル（ルートモデルバインディング）
     * @return RedirectResponse ジャンル一覧画面へのリダイレクト（flash: 削除した場合は success、書籍が紐づく場合は error）
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->exists()) {
            return redirect()
                ->route('genres.index')
                ->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }

        $genre->delete();

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
