<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 書籍登録のリクエスト（Web 画面）。入力内容のバリデーションを行う。
 */
class StoreBookRequest extends FormRequest
{
    /**
     * このリクエストを実行してよいかを判定する。
     *
     * 認可の判定はここでは行わず、常に許可する（ログイン必須は auth ミドルウェアで制御する）。
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
     * title・author は必須で255文字以内、isbn は必須で半角数字13桁かつ重複不可、published_date は必須の日付、
     * description は任意の文字列（文字数の上限なし）、image_url は任意の URL 形式で255文字以内、
     * genres は1つ以上必須の配列で、各要素は整数・重複なし・genres テーブルに存在する id。
     *
     * @return array<string, array<int, string>> 項目名をキー、ルールの配列を値にした連想配列
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'digits:13', 'unique:books,isbn'],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'distinct', 'exists:genres,id'],
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
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者は必須です。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.digits' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'image_url.url' => '画像URLは有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.array' => 'ジャンルは1つ以上選択してください。',
            'genres.min' => 'ジャンルは1つ以上選択してください。',
            'genres.*.integer' => '選択されたジャンルは存在しません。',
            'genres.*.distinct' => '選択されたジャンルは存在しません。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }
}
