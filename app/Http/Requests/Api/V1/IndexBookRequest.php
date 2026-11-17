<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 公開API（v1）の書籍一覧の検索条件のリクエスト。クエリパラメータのバリデーションを行う。
 */
class IndexBookRequest extends FormRequest
{
    /**
     * このリクエストを実行してよいかを判定する。
     *
     * 認可の判定はここでは行わず、常に許可する。
     *
     * @return bool 常に true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールを返す。
     *
     * keyword は任意の文字列で255文字以内、genre_id は任意の整数で genres テーブルに存在する id、
     * page は任意の整数で1以上、per_page は任意の整数で1〜100の範囲。
     *
     * @return array<string, array<int, string>> 項目名をキー、ルールの配列を値にした連想配列
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージを返す。
     *
     * 「項目名.ルール名」をキー、メッセージを値にした連想配列。
     *
     * @return array<string, string> エラーメッセージの連想配列
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre_id.integer' => '指定されたジャンルが存在しません。',
            'genre_id.exists' => '指定されたジャンルが存在しません。',
            'page.integer' => 'ページ番号は1以上の整数で指定してください。',
            'page.min' => 'ページ番号は1以上の整数で指定してください。',
            'per_page.integer' => '1ページあたりの件数は1〜100の範囲で指定してください。',
            'per_page.between' => '1ページあたりの件数は1〜100の範囲で指定してください。',
        ];
    }
}
