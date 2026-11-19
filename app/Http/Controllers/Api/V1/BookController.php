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
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

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

    public function show(Book $book): BookResource
    {
        return new BookResource($this->loadDetail($book));
    }

    /**
     * 書籍を登録し、ジャンルを紐付けて、登録した書籍を返す。
     *
     * 書籍の作成とジャンルの紐付けは1つのトランザクションで行う。
     *
     * @param  StoreBookRequest  $request  検証済みの登録リクエスト（user_id を含む）
     * @return JsonResponse 登録した書籍（BookResource）。ステータスは 201
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = DB::transaction(function () use ($request) {
            $book = Book::create($request->safe()->except('genres'));
            $book->genres()->sync($request->validated('genres'));

            return $book;
        });

        return (new BookResource($this->loadDetail($book)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 書籍を全体更新し、ジャンルの紐付けを入れ替えて、更新後の書籍を返す。
     *
     * 書籍の更新とジャンルの紐付けは1つのトランザクションで行う。
     *
     * @param  UpdateBookRequest  $request  検証済みの更新リクエスト（user_id を含む）
     * @param  Book  $book  更新する書籍
     * @return BookResource 更新後の書籍
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        DB::transaction(function () use ($request, $book) {
            $book->update($request->safe()->except('genres'));
            $book->genres()->sync($request->validated('genres'));
        });

        return new BookResource($this->loadDetail($book));
    }

    /**
     * 書籍を削除する。削除は1つのトランザクションで行う。
     *
     * @param  Book  $book  削除する書籍
     * @return Response ボディなしの 204
     */
    public function destroy(Book $book): Response
    {
        DB::transaction(fn () => $book->delete());

        return response()->noContent();
    }

    /**
     * 詳細・登録・更新で共通の形（ジャンル・新しい順のレビュー・平均評価・件数）にする。
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
