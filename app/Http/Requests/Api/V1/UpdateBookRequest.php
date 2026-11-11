<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\UpdateBookRequest as WebUpdateBookRequest;

/**
 * Web 版と同じルール・メッセージに、登録者ID（user_id）を加えたもの。
 */
class UpdateBookRequest extends WebUpdateBookRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
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
