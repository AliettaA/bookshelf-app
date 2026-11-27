<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 定期実行するコマンドを登録する。
     *
     * 時刻は config/app.php の timezone（Asia/Tokyo）が基準。
     *
     * @param  Schedule  $schedule  スケジューラー
     */
    protected function schedule(Schedule $schedule): void
    {
        // 読書計画の期限切れ化とリマインダー通知
        $schedule->command('reading-plans:process-daily')->dailyAt('20:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
