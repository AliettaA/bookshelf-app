<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * ISBN 検索（GET /books/isbn/{isbn}）のリクエスト。
 *
 * ルートパラメーターの isbn を検証する。JavaScript が data.error を表示するため、
 * 失敗時は {"error": "メッセージ"} の JSON（422）を返す。
 */
class IsbnSearchRequest extends FormRequest
{
    /**
     * このリクエストを実行できるかを返す（ログイン確認は auth ミドルウェアで行うため常に許可）。
     *
     * @return bool 常に true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ルートパラメーターの isbn を、バリデーション対象の入力に加える。
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['isbn' => $this->route('isbn')]);
    }

    /**
     * バリデーションルールを返す。
     *
     * @return array<string, array<int, string>> ISBN は半角数字13桁
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'digits:13'],
        ];
    }

    /**
     * バリデーションエラーの日本語メッセージを返す。
     *
     * @return array<string, string> 「項目名.ルール名」をキーにしたメッセージ
     */
    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBNは13桁の半角数字で入力してください。',
            'isbn.digits' => 'ISBNは13桁の半角数字で入力してください。',
        ];
    }

    /**
     * バリデーション失敗時に、{"error": "メッセージ"} の JSON（422）を返す。
     *
     * @param  Validator  $validator  失敗したバリデーター
     *
     * @throws HttpResponseException 422 の JSON レスポンスを返すために投げる
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['error' => $validator->errors()->first()], 422)
        );
    }
}
