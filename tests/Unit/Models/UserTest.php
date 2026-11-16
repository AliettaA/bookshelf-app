<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User モデルのリレーションのテスト（ケースID M1〜M4）。
 */
class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * M1 User は複数の書籍を持つ（books）
     */
    public function test_m1_user_has_many_books(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownBooks = Book::factory()->count(2)->for($user)->create();
        $othersBook = Book::factory()->for($otherUser)->create();

        $this->assertInstanceOf(HasMany::class, $user->books());
        $this->assertCount(2, $user->books);
        $this->assertEqualsCanonicalizing($ownBooks->pluck('id')->all(), $user->books->pluck('id')->all());
        $this->assertFalse($user->books->contains($othersBook));
    }

    /**
     * M2 User は複数のレビューを持つ（reviews）
     */
    public function test_m2_user_has_many_reviews(): void
    {
        $user = User::factory()->create();
        $ownReviews = Review::factory()->count(2)->for($user)->create();
        $othersReview = Review::factory()->create();

        $this->assertInstanceOf(HasMany::class, $user->reviews());
        $this->assertCount(2, $user->reviews);
        $this->assertEqualsCanonicalizing($ownReviews->pluck('id')->all(), $user->reviews->pluck('id')->all());
        $this->assertFalse($user->reviews->contains($othersReview));
    }

    /**
     * M3 User はお気に入り書籍を持つ（favoriteBooks）
     */
    public function test_m3_user_belongs_to_many_favorite_books(): void
    {
        $user = User::factory()->create();
        $favoriteBook = Book::factory()->create();
        $notFavoriteBook = Book::factory()->create();
        $user->favoriteBooks()->attach($favoriteBook->id);

        $this->assertInstanceOf(BelongsToMany::class, $user->favoriteBooks());
        $this->assertSame('favorites', $user->favoriteBooks()->getTable());
        $this->assertCount(1, $user->favoriteBooks);
        $this->assertTrue($user->favoriteBooks->first()->is($favoriteBook));
        $this->assertFalse($user->favoriteBooks->contains($notFavoriteBook));
        // 中間テーブルに created_at が記録される（withTimestamps）
        $this->assertNotNull($user->favoriteBooks->first()->pivot->created_at);
    }

    /**
     * M4 User はいいねしたレビューを持つ（likedReviews）
     */
    public function test_m4_user_belongs_to_many_liked_reviews(): void
    {
        $user = User::factory()->create();
        $likedReview = Review::factory()->create();
        $notLikedReview = Review::factory()->create();
        $user->likedReviews()->attach($likedReview->id);

        $this->assertInstanceOf(BelongsToMany::class, $user->likedReviews());
        $this->assertSame('review_likes', $user->likedReviews()->getTable());
        $this->assertCount(1, $user->likedReviews);
        $this->assertTrue($user->likedReviews->first()->is($likedReview));
        $this->assertFalse($user->likedReviews->contains($notLikedReview));
        // 中間テーブルに created_at が記録される（withTimestamps）
        $this->assertNotNull($user->likedReviews->first()->pivot->created_at);
    }
}
