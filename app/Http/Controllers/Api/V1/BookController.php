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

    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = Book::create($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return (new BookResource($this->loadDetail($book)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $book->update($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return new BookResource($this->loadDetail($book));
    }

    public function destroy(Book $book): Response
    {
        $book->delete();

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
