<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 公開API（v1）の書籍の一覧・詳細・登録・更新・削除を扱うコントローラー。
 */
class BookController extends Controller
{
    /**
     * 一覧で per_page が指定されないときの、1ページあたりの件数。
     */
    private const DEFAULT_PER_PAGE = 20;

    /**
     * 書籍の一覧を返す。
     *
     * keyword（タイトルまたは著者の部分一致）と genre_id（ジャンルでの絞り込み）で検索できる。
     * 新しい順（created_at の降順、同じ場合は id の降順）に、per_page 件（未指定は20件）ずつ返す。
     *
     * @param  IndexBookRequest  $request  検索条件（keyword・genre_id・page・per_page）のリクエスト
     * @return AnonymousResourceCollection ページネーション付きの書籍リソース（平均評価・レビュー件数・ジャンル付き）
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $keyword = $request->validated('keyword');
        $genreId = $request->validated('genre_id');

        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($keyword !== null, function ($query) use ($keyword) {
                // LIKE のワイルドカード（% と _）は文字として扱う
                $pattern = '%'.addcslashes($keyword, '\\%_').'%';

                // OR で genre_id の絞り込みが外れないよう、keyword の条件はグループ化する
                $query->where(function ($query) use ($pattern) {
                    $query->where('title', 'like', $pattern)
                        ->orWhere('author', 'like', $pattern);
                });
            })
            ->when($genreId !== null, function ($query) use ($genreId) {
                $query->whereHas('genres', fn ($query) => $query->where('genres.id', $genreId));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->validated('per_page') ?? self::DEFAULT_PER_PAGE)
            ->withQueryString();

        return BookResource::collection($books);
    }

    /**
     * 書籍の詳細を返す。
     *
     * ジャンル、レビュー（新しい順）、平均評価、レビュー件数を含む。
     * 存在しない書籍は 404（Handler で日本語メッセージの JSON に変換される）。
     *
     * @param  Book  $book  取得する書籍（ルートモデルバインディング）
     * @return BookResource 書籍リソース
     */
    public function show(Book $book): BookResource
    {
        return new BookResource($this->loadDetail($book));
    }

    /**
     * 書籍を登録する。
     *
     * リクエストの user_id を登録者として書籍を作成し、ジャンルを紐づける。
     * 登録した書籍を、詳細と同じ形で返す。
     *
     * @param  StoreBookRequest  $request  バリデーション済みの登録リクエスト（user_id を含む）
     * @return JsonResponse 登録した書籍のリソース（ステータス 201）
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = Book::create($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return (new BookResource($this->loadDetail($book)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 書籍を更新する（PUT による全体更新）。
     *
     * リクエストの user_id を含めて書籍の内容を更新し、ジャンルの紐づけを指定された内容に置き換える。
     * 更新後の書籍を、詳細と同じ形で返す。
     *
     * @param  UpdateBookRequest  $request  バリデーション済みの更新リクエスト（user_id を含む）
     * @param  Book  $book  更新する書籍（ルートモデルバインディング）
     * @return BookResource 更新後の書籍リソース（ステータス 200）
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $book->update($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return new BookResource($this->loadDetail($book));
    }

    /**
     * 書籍を削除する。
     *
     * 関連するレビュー・いいね・お気に入り・ジャンルの紐づけは、外部キーの cascadeOnDelete で一緒に削除される。
     *
     * @param  Book  $book  削除する書籍（ルートモデルバインディング）
     * @return Response ボディなしのレスポンス（ステータス 204）
     */
    public function destroy(Book $book): Response
    {
        $book->delete();

        return response()->noContent();
    }

    /**
     * 詳細・登録・更新で共通の形（ジャンル・新しい順のレビュー・平均評価・件数）にする。
     *
     * ジャンル、レビュー（新しい順）、レビューの投稿者を読み込み、レビューの平均評価とレビュー件数を付ける。
     *
     * @param  Book  $book  読み込み対象の書籍
     * @return Book 関連データを読み込み済みの書籍
     */
    private function loadDetail(Book $book): Book
    {
        return $book
            ->load([
                'genres',
                'reviews' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
                'reviews.user',
            ])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');
    }
}
