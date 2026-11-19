<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleBooksException;
use App\Http\Requests\IsbnSearchRequest;
use App\Services\GoogleBooksService;
use Illuminate\Http\JsonResponse;

/**
 * 書籍の登録・編集画面の ISBN 検索（Google Books から書籍情報を取得）を扱うコントローラー。
 */
class BookIsbnController extends Controller
{
    /**
     * ISBN に該当する書籍情報を JSON で返す。
     *
     * - 成功：title・author・description・image_url・published_date（200）
     * - ISBN が半角数字13桁でない：{"error": ...}（422。IsbnSearchRequest が返す）
     * - 該当する書籍がない：{"error": ...}（404）
     * - Google Books API との通信に失敗：{"error": ...}（502）
     *
     * @param  IsbnSearchRequest  $request  検証済みのリクエスト（ISBN は半角数字13桁）
     * @param  GoogleBooksService  $googleBooksService  Google Books API を呼び出すサービス
     * @return JsonResponse 書籍情報、またはエラーメッセージの JSON
     */
    public function __invoke(IsbnSearchRequest $request, GoogleBooksService $googleBooksService): JsonResponse
    {
        try {
            $book = $googleBooksService->findByIsbn($request->validated('isbn'));
        } catch (GoogleBooksException) {
            return response()->json([
                'error' => '書籍情報の取得に失敗しました。時間をおいて再度お試しください。',
            ], 502);
        }

        if ($book === null) {
            return response()->json(['error' => '該当する書籍が見つかりませんでした。'], 404);
        }

        return response()->json($book);
    }
}
