<?php

namespace App\Services;

use App\Exceptions\GoogleBooksException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Books API で、ISBN から書籍情報を取得する。
 *
 * エンドポイント・API キー・タイムアウトは config/services.php の google_books から読む。
 * API キー（.env の GOOGLE_BOOKS_API_KEY）が空の場合は、キーなしで呼び出す。
 */
class GoogleBooksService
{
    /**
     * ISBN に該当する書籍の情報を取得する。
     *
     * 検索結果が複数ある場合は、先頭の1件を使う。
     * 項目がない場合は、その項目を null にする。
     * - author：authors（配列）を「、」で連結する
     * - image_url：imageLinks.thumbnail を使い、http:// で始まる場合は https:// に置き換える
     * - published_date：Google Books の publishedDate をそのまま返す（「2013」「2013-06」のような形もある）
     *
     * @param  string  $isbn  検索する ISBN
     * @return array{title: string|null, author: string|null, description: string|null, image_url: string|null, published_date: string|null}|null 書籍情報。該当する書籍がなければ null
     *
     * @throws GoogleBooksException 通信に失敗した場合（タイムアウト・エラー応答・解釈できない応答）
     */
    public function findByIsbn(string $isbn): ?array
    {
        $json = $this->requestVolumes($isbn);

        $items = $json['items'] ?? [];

        if (! is_array($items) || $items === []) {
            return null;
        }

        $volumeInfo = $items[0]['volumeInfo'] ?? null;

        if (! is_array($volumeInfo)) {
            throw new GoogleBooksException('Google Books API の応答に volumeInfo がありません。');
        }

        return [
            'title' => $this->stringOrNull($volumeInfo['title'] ?? null),
            'author' => $this->joinAuthors($volumeInfo['authors'] ?? null),
            'description' => $this->stringOrNull($volumeInfo['description'] ?? null),
            'image_url' => $this->toHttps($this->stringOrNull($volumeInfo['imageLinks']['thumbnail'] ?? null)),
            'published_date' => $this->stringOrNull($volumeInfo['publishedDate'] ?? null),
        ];
    }

    /**
     * Google Books API の volumes を ISBN で検索し、応答の JSON を配列で返す。
     *
     * @param  string  $isbn  検索する ISBN
     * @return array<string, mixed> 応答の JSON
     *
     * @throws GoogleBooksException 接続できない・タイムアウト・エラー応答・JSON でない応答のとき
     */
    private function requestVolumes(string $isbn): array
    {
        $query = ['q' => 'isbn:'.$isbn];

        $apiKey = config('services.google_books.key');

        if (filled($apiKey)) {
            $query['key'] = $apiKey;
        }

        try {
            $response = Http::timeout(config('services.google_books.timeout'))
                ->acceptJson()
                ->get(config('services.google_books.endpoint'), $query);
        } catch (ConnectionException $exception) {
            throw new GoogleBooksException('Google Books API に接続できませんでした。', previous: $exception);
        }

        if ($response->failed()) {
            throw new GoogleBooksException('Google Books API がエラーを返しました（HTTP '.$response->status().'）。');
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new GoogleBooksException('Google Books API の応答を JSON として解釈できません。');
        }

        return $json;
    }

    /**
     * 著者の配列を「、」で連結する。
     *
     * @param  mixed  $authors  Google Books の authors（文字列の配列）
     * @return string|null 連結した著者名。配列でない・文字列の要素がない場合は null
     */
    private function joinAuthors(mixed $authors): ?string
    {
        if (! is_array($authors)) {
            return null;
        }

        $names = collect($authors)->filter(fn ($author) => is_string($author) && $author !== '');

        return $names->isEmpty() ? null : $names->implode('、');
    }

    /**
     * URL が http:// で始まる場合に、https:// に置き換える。
     *
     * @param  string|null  $url  画像の URL
     * @return string|null 置き換え後の URL。null はそのまま null
     */
    private function toHttps(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return str_starts_with($url, 'http://') ? 'https://'.substr($url, strlen('http://')) : $url;
    }

    /**
     * 文字列のときだけその値を、それ以外（項目なし・型が違う）は null を返す。
     *
     * @param  mixed  $value  Google Books の項目の値
     * @return string|null 文字列、または null
     */
    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
