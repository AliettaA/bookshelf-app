<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
