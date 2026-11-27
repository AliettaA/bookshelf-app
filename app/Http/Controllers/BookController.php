<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * 書籍一覧を、キーワード検索・ジャンル絞り込み・並び替えをして10件ずつ表示する。
     *
     * - keyword：タイトルまたは著者の部分一致（% と _ は文字として扱う）。where をグループ化するので、ジャンルの絞り込みは外れない
     * - genre：そのジャンルに紐付く書籍だけ。数字でない値や存在しないIDはエラーにせず、該当なし（0件）にする
     * - sort：newest（新しい順）・oldest（古い順）・rating（評価順）・title（タイトル順）。未指定や不正な値は newest として扱う
     *
     * ページを移っても検索条件を引き継ぐ（withQueryString）。
     *
     * @param  Request  $request  リクエスト（クエリの keyword・genre・sort を使う）
     * @return View 書籍一覧画面（books.index）。$books と、絞り込み用の $genres（id 順）を渡す
     */
    public function index(Request $request): View
    {
        $keyword = $request->query('keyword');
        $keyword = is_string($keyword) && $keyword !== '' ? $keyword : null;

        $genreId = $request->query('genre');
        $genreId = is_string($genreId) && $genreId !== '' ? $genreId : null;

        $sort = $request->query('sort');
        $sort = is_string($sort) ? $sort : 'newest';

        $query = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($keyword !== null, function (Builder $query) use ($keyword) {
                // LIKE のワイルドカード（% と _）は文字として扱う
                $pattern = '%'.addcslashes($keyword, '\\%_').'%';

                // OR で genre の絞り込みが外れないよう、keyword の条件はグループ化する
                $query->where(function (Builder $query) use ($pattern) {
                    $query->where('title', 'like', $pattern)
                        ->orWhere('author', 'like', $pattern);
                });
            })
            ->when($genreId !== null, function (Builder $query) use ($genreId) {
                // 数字だけの値のみ ID として使う（'1abc' のような値が MySQL で 1 に変換されるのを防ぐ）
                ctype_digit($genreId)
                    ? $query->whereHas('genres', fn (Builder $query) => $query->where('genres.id', $genreId))
                    : $query->whereIn('id', []);
            });

        $books = $this->applySort($query, $sort)
            ->paginate(10)
            ->withQueryString();

        $genres = Genre::orderBy('id')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * 並び替えの条件を書籍のクエリに加える。
     *
     * - oldest：created_at 昇順 → id 昇順
     * - title：タイトル昇順 → id 昇順
     * - rating：平均評価の降順（レビューがない書籍は最後。MySQL は降順で NULL を最後に並べる）→ レビュー件数の降順 → id 降順
     * - それ以外（newest・未指定・不正な値）：created_at 降順 → id 降順
     *
     * rating の並び替えには、withAvg と withCount で付けた reviews_avg_rating と reviews_count を使う。
     *
     * @param  Builder<Book>  $query  並び替え前の書籍のクエリ
     * @param  string  $sort  並び替えの指定（newest / oldest / rating / title）
     * @return Builder<Book> 並び替えを加えたクエリ
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'title' => $query->orderBy('title')->orderBy('id'),
            'rating' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    public function create(): View
    {
        $genres = Genre::orderBy('id')->get();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍を登録し、ジャンルを紐付けて、登録した書籍の詳細へ移動する。
     *
     * 書籍の作成とジャンルの紐付けは1つのトランザクションで行う。
     *
     * @param  StoreBookRequest  $request  検証済みの登録リクエスト
     * @return RedirectResponse 書籍詳細へのリダイレクト（success：書籍を登録しました。）
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request) {
            $book = $request->user()->books()->create($request->safe()->except('genres'));
            $book->genres()->sync($request->validated('genres'));

            return $book;
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

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

    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');
        $genres = Genre::orderBy('id')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍を更新し、ジャンルの紐付けを入れ替えて、書籍の詳細へ移動する。
     *
     * 書籍の更新とジャンルの紐付けは1つのトランザクションで行う。
     *
     * @param  UpdateBookRequest  $request  検証済みの更新リクエスト
     * @param  Book  $book  更新する書籍
     * @return RedirectResponse 書籍詳細へのリダイレクト（success：書籍情報を更新しました。）
     *
     * @throws AuthorizationException 登録者本人でない場合（403）
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        DB::transaction(function () use ($request, $book) {
            $book->update($request->safe()->except('genres'));
            $book->genres()->sync($request->validated('genres'));
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除して、書籍一覧へ移動する。
     *
     * レビュー・お気に入り・ジャンル紐付け・読書計画などは外部キーの cascadeOnDelete で一緒に消える。
     * 読書計画の通知は外部キーで消えないため、書籍を削除する前に計画の ID を取り出して削除する。
     * 通知と書籍の削除は1つのトランザクションで行う。
     *
     * @param  Book  $book  削除する書籍
     * @return RedirectResponse 書籍一覧へのリダイレクト（success：書籍を削除しました。）
     *
     * @throws AuthorizationException 登録者本人でない場合（403）
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        DB::transaction(function () use ($book): void {
            ReadingPlan::deleteNotificationsFor($book->readingPlans()->pluck('id'));
            $book->delete();
        });

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
