<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * ユーザーモデル（users テーブル）。
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 一括代入を許可する属性。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * 配列・JSON に変換するときに隠す属性。
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 属性のキャスト設定。
     *
     * email_verified_at は日時型、password は保存時に自動でハッシュ化する。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * このユーザーが登録した書籍。
     *
     * @return HasMany<Book>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * このユーザーが投稿したレビュー。
     *
     * @return HasMany<Review>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * このユーザーがお気に入りに追加した書籍（中間テーブル favorites）。
     *
     * 中間テーブルの created_at・updated_at も記録する。
     *
     * @return BelongsToMany<Book>
     */
    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites')->withTimestamps();
    }

    /**
     * このユーザーがいいねしたレビュー（中間テーブル review_likes）。
     *
     * 中間テーブルの created_at・updated_at も記録する。
     *
     * @return BelongsToMany<Review>
     */
    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_likes')->withTimestamps();
    }
}
