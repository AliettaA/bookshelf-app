<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * 読書計画を6件登録する（ID 1〜6）。
     *
     * 実行日に関わらず同じ挙動になるよう、target_date は Carbon::today() を起点にする。
     * 動作確認の効率のため、主要なシナリオは山田太郎（ID 1〜5）に集約し、
     * 他ユーザーの認可テスト用に鈴木花子の計画（ID 6）を1件置く。
     * 同じユーザーの進行中の計画が同じ書籍で重複しないよう、計画ごとに異なる書籍を割り当てる。
     */
    public function run(): void
    {
        $userIdsByEmail = User::pluck('id', 'email');
        $bookIds = Book::orderBy('id')->pluck('id')->values();
        $today = Carbon::today();

        collect([
            // 山田太郎（ID 1〜5）
            // 1. 3日前リマインダーの対象
            ['yamada@example.com', $today->copy()->addDays(3), ReadingPlanStatus::InProgress, null],
            // 2. 当日リマインダーの対象
            ['yamada@example.com', $today->copy(), ReadingPlanStatus::InProgress, null],
            // 3. バッチで期限切れにする対象 ＋ 3日後の再エンゲージメントの対象
            ['yamada@example.com', $today->copy()->subDays(3), ReadingPlanStatus::InProgress, null],
            // 4. リマインダーの対象外
            ['yamada@example.com', $today->copy()->addDays(7), ReadingPlanStatus::InProgress, null],
            // 5. 読了済み
            ['yamada@example.com', $today->copy()->subDays(10), ReadingPlanStatus::Completed, $today->copy()->subDays(5)],
            // 鈴木花子（ID 6）：山田太郎でログインして /reading-plans/6/edit を開き、403 になることを確認する用
            ['suzuki@example.com', $today->copy()->addDays(5), ReadingPlanStatus::InProgress, null],
        ])->each(fn (array $plan, int $index) => ReadingPlan::create([
            'user_id' => $userIdsByEmail[$plan[0]],
            'book_id' => $bookIds[$index],
            'target_date' => $plan[1],
            'status' => $plan[2],
            'completed_at' => $plan[3],
        ]));
    }
}
