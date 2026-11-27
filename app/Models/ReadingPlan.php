<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 読書計画（ユーザーが書籍ごとに立てる、読み終える期日の計画）。
 */
class ReadingPlan extends Model
{
    use HasFactory;

    /**
     * 一括代入を許可する属性。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'status',
        'completed_at',
    ];

    /**
     * 属性のキャスト設定。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'status' => ReadingPlanStatus::class,
    ];

    /**
     * この計画を立てたユーザー。
     *
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この計画の対象の書籍。
     *
     * @return BelongsTo<Book>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * 状態が進行中の計画だけに絞り込むスコープ。
     *
     * @param  Builder<ReadingPlan>  $query  絞り込み対象のクエリ
     * @return Builder<ReadingPlan> 進行中の計画に絞り込んだクエリ
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::InProgress);
    }

    /**
     * 期日を変更する。期限切れの計画だった場合は、状態を進行中に戻す。
     *
     * @param  string  $targetDate  新しい期日（Y-m-d）
     */
    public function reschedule(string $targetDate): void
    {
        $this->target_date = $targetDate;

        if ($this->status === ReadingPlanStatus::Expired) {
            $this->status = ReadingPlanStatus::InProgress;
        }

        $this->save();
    }

    /**
     * 計画を完了にし、操作した日時を completed_at に記録する。
     */
    public function markAsCompleted(): void
    {
        $this->status = ReadingPlanStatus::Completed;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * 計画を、この計画の通知と一緒に削除する。
     */
    public function deleteWithNotifications(): void
    {
        DB::transaction(function (): void {
            self::deleteNotificationsFor(collect([$this->id]));

            $this->delete();
        });
    }

    /**
     * 指定した読書計画の通知を削除する。
     *
     * 通知は外部キーで紐付いていないため、data の reading_plan_id が一致するものを削除する。
     * 読書計画や書籍の削除と一緒に使うので、呼び出し側で DB::transaction() の中に入れる。
     * 書籍の削除では計画が外部キーで一緒に消えるため、書籍を削除する前に計画の ID を取り出して渡す。
     *
     * @param  Collection<int, int>  $readingPlanIds  読書計画の ID（空の場合は何も削除しない）
     */
    public static function deleteNotificationsFor(Collection $readingPlanIds): void
    {
        DatabaseNotification::query()
            ->whereIn('data->reading_plan_id', $readingPlanIds->all())
            ->delete();
    }
}
