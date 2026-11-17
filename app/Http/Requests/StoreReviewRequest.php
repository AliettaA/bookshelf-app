<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * レビュー投稿のリクエスト。入力内容のバリデーションを行う。
 */
class StoreReviewRequest extends FormRequest
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
     * rating は必須の整数で1〜5の範囲、comment は必須の文字列で1000文字以内。
     *
     * @return array<string, array<int, string>> 項目名をキー、ルールの配列を値にした連想配列
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
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
            'rating.required' => '評価は必須です。',
            'rating.integer' => '評価は1〜5の範囲で選択してください。',
            'rating.between' => '評価は1〜5の範囲で選択してください。',
            'comment.required' => 'コメントは必須です。',
            'comment.string' => 'コメントは1000文字以内で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }
}
