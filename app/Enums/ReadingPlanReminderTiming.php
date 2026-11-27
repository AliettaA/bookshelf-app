<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * 読書計画のリマインダー通知の種類。
 *
 * 値は通知の data の timing に保存し、通知一覧画面の色とアイコンの出し分けに使う。
 * 種類ごとの対象の条件（状態・期日）と、通知の文言（PM 指定）をここにまとめる。
 */
enum ReadingPlanReminderTiming: string
{
    case ThreeDaysBefore = 'three_days_before';
    case OnDueDate = 'on_due_date';
    case ThreeDaysAfter = 'three_days_after';

    /**
     * 通知のタイトルを返す。
     *
     * @return string 通知のタイトル
     */
    public function title(): string
    {
        return match ($this) {
            self::ThreeDaysBefore => '読書計画リマインド — 期限まであと 3 日',
            self::OnDueDate => '読書計画 — 本日が期限',
            self::ThreeDaysAfter => '読書計画 — 期限超過 3 日経過',
        };
    }

    /**
     * 通知の本文を返す。
     *
     * @param  string  $bookTitle  対象の書籍のタイトル
     * @return string 書籍のタイトルを埋め込んだ本文
     */
    public function body(string $bookTitle): string
    {
        return match ($this) {
            self::ThreeDaysBefore => "「{$bookTitle}」の期限まで残り 3 日です。引き続き読書を進めましょう。",
            self::OnDueDate => "「{$bookTitle}」は本日が期限です。読了済みなら完了登録を、もう少し必要なら期限を変更してください。",
            self::ThreeDaysAfter => "「{$bookTitle}」の期限から 3 日が経過しました。読了済みなら完了登録、続けるなら期限を変更してください。",
        };
    }

    /**
     * この通知の対象になる読書計画の状態を返す。
     *
     * @return ReadingPlanStatus 3日前・当日は進行中、3日後は期限切れ
     */
    public function targetStatus(): ReadingPlanStatus
    {
        return match ($this) {
            self::ThreeDaysBefore, self::OnDueDate => ReadingPlanStatus::InProgress,
            self::ThreeDaysAfter => ReadingPlanStatus::Expired,
        };
    }

    /**
     * この通知の対象になる読書計画の期日を返す。
     *
     * @param  CarbonInterface  $today  基準にする今日の日付（この引数の値は変更しない）
     * @return CarbonInterface 3日前なら今日の3日後、当日なら今日、3日後なら今日の3日前
     */
    public function targetDate(CarbonInterface $today): CarbonInterface
    {
        return match ($this) {
            self::ThreeDaysBefore => $today->copy()->addDays(3),
            self::OnDueDate => $today->copy(),
            self::ThreeDaysAfter => $today->copy()->subDays(3),
        };
    }
}
