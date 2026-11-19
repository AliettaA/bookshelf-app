<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

/**
 * 読書計画の認可。他人の計画は操作できず、完了した計画は編集・読了できない。
 */
class ReadingPlanPolicy
{
    /**
     * 計画を編集（編集画面の表示・更新）できるかを判定する。
     *
     * 計画の持ち主本人で、かつ状態が完了でない場合のみ許可する。
     *
     * @param  User  $user  操作しようとしているユーザー
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @return bool 許可する場合は true
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $this->isOwner($user, $readingPlan)
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * 計画を削除できるかを判定する。
     *
     * 持ち主本人であれば、状態に関わらず許可する。
     *
     * @param  User  $user  操作しようとしているユーザー
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @return bool 許可する場合は true
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $this->isOwner($user, $readingPlan);
    }

    /**
     * 計画を読了（完了にする）できるかを判定する。
     *
     * 計画の持ち主本人で、かつ状態が完了でない（進行中または期限切れ）場合のみ許可する。
     *
     * @param  User  $user  操作しようとしているユーザー
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @return bool 許可する場合は true
     */
    public function complete(User $user, ReadingPlan $readingPlan): bool
    {
        return $this->isOwner($user, $readingPlan)
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * 計画の持ち主本人かどうかを判定する。
     *
     * @param  User  $user  操作しようとしているユーザー
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @return bool 本人の場合は true
     */
    private function isOwner(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
