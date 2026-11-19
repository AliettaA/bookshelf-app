<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 読書計画の作成リクエスト。
 */
class StoreReadingPlanRequest extends FormRequest
{
    /**
     * リクエストを許可するか判定する（ログイン済みは routes の auth ミドルウェアで確認済み）。
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'book_id' => ['bail', 'required', 'integer', 'exists:books,id'],
            'target_date' => ['bail', 'required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * エラーメッセージ（日本語）を返す。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.required' => '書籍を選択してください。',
            'book_id.integer' => '選択された書籍は存在しません。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
        ];
    }

    /**
     * 基本ルールの検証後に、重複チェックを追加する。
     *
     * ログインユーザーが同じ書籍で進行中の計画をすでに持っている場合は、book_id のエラーにする。
     *
     * @param  Validator  $validator  バリデーター
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('book_id')) {
                return;
            }

            $hasInProgressPlan = $this->user()
                ->readingPlans()
                ->inProgress()
                ->where('book_id', $this->integer('book_id'))
                ->exists();

            if ($hasInProgressPlan) {
                $validator->errors()->add('book_id', 'この書籍は既に進行中の読書計画が存在します。');
            }
        });
    }
}
