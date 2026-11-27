<?php

namespace App\Notifications;

use App\Enums\ReadingPlanReminderTiming;
use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

/**
 * 読書計画のリマインダー通知。notifications テーブルに保存する。
 */
class ReadingPlanReminder extends Notification
{
    /**
     * 通知を作成する。
     *
     * 本文に書籍のタイトルを使うため、まとめて送る場合は呼び出し側で book を読み込んでおく。
     *
     * @param  ReadingPlan  $readingPlan  通知の対象の読書計画
     * @param  ReadingPlanReminderTiming  $timing  通知の種類
     */
    public function __construct(
        private readonly ReadingPlan $readingPlan,
        private readonly ReadingPlanReminderTiming $timing,
    ) {}

    /**
     * 通知を送るチャンネルを返す。
     *
     * @param  object  $notifiable  通知を受け取る対象
     * @return array<int, string> database チャンネルのみ
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * notifications テーブルの data に保存する内容を返す。
     *
     * @param  object  $notifiable  通知を受け取る対象
     * @return array{timing: string, title: string, body: string, reading_plan_id: int} 通知の種類・タイトル・本文・読書計画の ID
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'timing' => $this->timing->value,
            'title' => $this->timing->title(),
            'body' => $this->timing->body($this->readingPlan->book->title),
            'reading_plan_id' => $this->readingPlan->id,
        ];
    }
}
