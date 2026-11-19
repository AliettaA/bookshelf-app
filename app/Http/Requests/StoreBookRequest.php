<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 書籍登録のバリデーションルールを返す。
     *
     * ISBN は任意（入力する場合は半角数字13桁で、他の書籍と重複しない）。出版日も任意（入力する場合は日付形式）。
     *
     * @return array<string, array<int, string>> 項目名をキーにしたルール
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'digits:13', 'unique:books,isbn'],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'distinct', 'exists:genres,id'],
        ];
    }

    /**
     * バリデーションエラーの日本語メッセージを返す。
     *
     * ISBN と出版日は任意のため、「必須です」のメッセージは持たない。
     *
     * @return array<string, string> 「項目名.ルール名」をキーにしたメッセージ
     */
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者は必須です。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.digits' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
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
