<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ジャンル登録のリクエスト。入力内容のバリデーションを行う。
 */
class StoreGenreRequest extends FormRequest
{
    /**
     * このリクエストを実行してよいかを判定する。
     *
     * ジャンルには Policy がないため、認可の判定はここでは行わず、常に許可する（ログイン必須は auth ミドルウェアで制御する）。
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
     * name は必須の文字列で255文字以内、genres テーブル内で重複不可。
     *
     * @return array<string, array<int, string>> 項目名をキー、ルールの配列を値にした連想配列
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:genres,name'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージを返す。
     *
     * 「項目名.ルール名」をキー、メッセージを値にした連想配列。
     * name は会員登録の「お名前」と同じキーのため、メッセージ内で「ジャンル名」と明示している。
     *
     * @return array<string, string> エラーメッセージの連想配列
     */
    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名は必須です。',
            'name.string' => 'ジャンル名は255文字以内で入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'そのジャンル名は既に使用されています。',
        ];
    }
}
