<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanReminderTiming;
use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 読書計画の日次バッチ。期限切れ化とリマインダー通知を行う。
 *
 * 1日1回の実行を前提にしており、同じ日に再実行すると同じ通知がもう一度送られる。
 * 実行しなかった日の通知を、後からまとめて送ることはしない。
 */
class ProcessDailyReadingPlans extends Command
{
    /**
     * コマンド名。
     *
     * @var string
     */
    protected $signature = 'reading-plans:process-daily';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = '期日を過ぎた読書計画を期限切れにして、リマインダー通知を送る';

    /**
     * 期限切れ化と通知の送信を1つのトランザクションで実行し、件数を表示する。
     *
     * 3日後の通知は期限切れの計画が対象なので、必ず期限切れ化を先に行う。
     * 処理の途中で日付が変わっても基準がずれないよう、今日の日付は最初に1回だけ取得する。
     *
     * @return int 終了コード（正常終了は 0）
     */
    public function handle(): int
    {
        $today = today();

        [$expiredCount, $sentCounts] = DB::transaction(fn (): array => [
            $this->expireOverduePlans($today),
            collect(ReadingPlanReminderTiming::cases())
                ->mapWithKeys(fn (ReadingPlanReminderTiming $timing): array => [
                    $timing->value => $this->sendReminders($timing, $today),
                ]),
        ]);

        $this->info("期限切れにした計画：{$expiredCount}件");
        $sentCounts->each(fn (int $count, string $timing) => $this->info("通知（{$timing}）：{$count}件"));

        return self::SUCCESS;
    }

    /**
     * 進行中で期日が今日より前の読書計画を、期限切れにする。
     *
     * @param  CarbonInterface  $today  基準にする今日の日付
     * @return int 期限切れにした件数
     */
    private function expireOverduePlans(CarbonInterface $today): int
    {
        return ReadingPlan::query()
            ->inProgress()
            ->where('target_date', '<', $today->toDateString())
            ->update(['status' => ReadingPlanStatus::Expired]);
    }

    /**
     * 指定した種類の条件に合う読書計画の登録者に、通知を送る。
     *
     * @param  ReadingPlanReminderTiming  $timing  通知の種類
     * @param  CarbonInterface  $today  基準にする今日の日付
     * @return int 通知を送った件数
     */
    private function sendReminders(ReadingPlanReminderTiming $timing, CarbonInterface $today): int
    {
        return ReadingPlan::query()
            ->with(['user', 'book'])
            ->where('status', $timing->targetStatus())
            ->where('target_date', $timing->targetDate($today)->toDateString())
            ->get()
            ->each(fn (ReadingPlan $readingPlan) => $readingPlan->user->notify(
                new ReadingPlanReminder($readingPlan, $timing)
            ))
            ->count();
    }
}
