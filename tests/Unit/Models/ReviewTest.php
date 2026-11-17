<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review モデルのリレーションのテスト（ケースID M9〜M10）。
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * M9 Review は投稿者・書籍に属する（user / book）
     */
    public function test_m9_review_belongs_to_user_and_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($user)->for($book)->create();

        $this->assertInstanceOf(BelongsTo::class, $review->user());
        $this->assertSame('user_id', $review->user()->getForeignKeyName());
        $this->assertInstanceOf(User::class, $review->user);
        $this->assertTrue($review->user->is($user));

        $this->assertInstanceOf(BelongsTo::class, $review->book());
        $this->assertSame('book_id', $review->book()->getForeignKeyName());
        $this->assertInstanceOf(Book::class, $review->book);
        $this->assertTrue($review->book->is($book));
    }

    /**
     * M10 Review はいいねしたユーザーを持つ（likedByUsers）
     */
    public function test_m10_review_belongs_to_many_liked_by_users(): void
    {
        $review = Review::factory()->create();
        $likers = User::factory()->count(2)->create();
        $notLiker = User::factory()->create();
        $review->likedByUsers()->attach($likers->pluck('id'));

        $this->assertInstanceOf(BelongsToMany::class, $review->likedByUsers());
        $this->assertSame('review_likes', $review->likedByUsers()->getTable());
        $this->assertCount(2, $review->likedByUsers);
        $this->assertEqualsCanonicalizing($likers->pluck('id')->all(), $review->likedByUsers->pluck('id')->all());
        $this->assertFalse($review->likedByUsers->contains($notLiker));
        // 中間テーブルに created_at が記録される（withTimestamps）
        $this->assertNotNull($review->likedByUsers->first()->pivot->created_at);
    }
}
