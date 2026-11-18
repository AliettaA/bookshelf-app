<?php

namespace App\Enums;

/**
 * 読書計画の状態。
 *
 * reading_plans.status に文字列（string backed enum の値）で保存する。
 */
enum ReadingPlanStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';

    /**
     * 画面に表示する日本語のラベルを返す。
     *
     * @return string 進行中／読了／期限切れ
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => '進行中',
            self::Completed => '読了',
            self::Expired => '期限切れ',
        };
    }

    /**
     * 状態バッジに付ける Tailwind CSS のクラスを返す。
     *
     * tailwind.config.js は app/ 配下を走査しないため、Blade で既に使われているクラスだけで組み合わせている。
     *
     * @return string 背景色と文字色のクラス（スペース区切り）
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::InProgress => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-700',
            self::Expired => 'bg-red-100 text-red-700',
        };
    }
}
