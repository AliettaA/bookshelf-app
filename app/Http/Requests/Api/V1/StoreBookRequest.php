<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\StoreBookRequest as WebStoreBookRequest;

/**
 * Web 版と同じルール・メッセージに、登録者ID（user_id）を加えたもの。
 */
class StoreBookRequest extends WebStoreBookRequest
{
    /**
     * バリデーションルールを返す。
     *
     * Web 版の StoreBookRequest のルールに、登録者ID（user_id：必須の整数で users テーブルに存在する id）を加える。
     *
     * @return array<string, array<int, mixed>> 項目名をキー、ルールの配列を値にした連想配列
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージを返す。
     *
     * Web 版の StoreBookRequest のメッセージに、user_id のメッセージを加える。
     *
     * @return array<string, string> エラーメッセージの連想配列
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'user_id.required' => '登録者IDは必須です。',
            'user_id.integer' => '指定された登録者は存在しません。',
            'user_id.exists' => '指定された登録者は存在しません。',
        ];
    }
}
