<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 書籍モデル（books テーブル）。
 */
class Book extends Model
{
    use HasFactory;

    /**
     * 一括代入を許可する属性。
     *
     * published_date は Blade が Y-m-d の文字列のまま出力するため、日付キャストはしない。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    /**
     * この書籍を登録したユーザー。
     *
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この書籍に紐づくジャンル（中間テーブル book_genre）。
     *
     * 中間テーブルの created_at・updated_at も記録する。
     *
     * @return BelongsToMany<Genre>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'book_genre')->withTimestamps();
    }

    /**
     * この書籍に投稿されたレビュー。
     *
     * @return HasMany<Review>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
