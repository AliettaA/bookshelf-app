<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 読書計画の更新リクエスト（期日の変更）。
 */
class UpdateReadingPlanRequest extends FormRequest
{
    /**
     * リクエストを許可するか判定する（本人かどうかは ReadingPlanPolicy でコントローラーが確認する）。
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
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
        ];
    }

    /**
     * 基本ルールの検証後に、重複チェックを追加する。
     *
     * 期限切れの計画は更新すると進行中に戻るため、同じユーザーが同じ書籍で
     * 別の進行中の計画を持っている場合はエラーにする（自分自身は除く）。
     * 編集画面には book_id の入力欄がないので、エラーは target_date に付ける。
     *
     * @param  Validator  $validator  バリデーター
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ReadingPlan $readingPlan */
            $readingPlan = $this->route('reading_plan');

            if ($validator->errors()->has('target_date') || $readingPlan->status !== ReadingPlanStatus::Expired) {
                return;
            }

            $hasOtherInProgressPlan = ReadingPlan::query()
                ->inProgress()
                ->where('user_id', $readingPlan->user_id)
                ->where('book_id', $readingPlan->book_id)
                ->whereKeyNot($readingPlan->getKey())
                ->exists();

            if ($hasOtherInProgressPlan) {
                $validator->errors()->add('target_date', 'この書籍は既に進行中の読書計画が存在します。');
            }
        });
    }
}
