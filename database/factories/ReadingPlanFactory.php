<?php

namespace Database\Factories;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    /**
     * モデルの既定の状態を定義する。
     *
     * 既定は「進行中・期日は明日以降・未読了」の計画。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'target_date' => fake()->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ];
    }
}
