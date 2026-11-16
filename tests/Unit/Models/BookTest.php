<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Book モデルのリレーションのテスト（ケースID M5〜M7）。
 */
class BookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * M5 Book は登録者に属する（user）
     */
    public function test_m5_book_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->assertInstanceOf(BelongsTo::class, $book->user());
        $this->assertSame('user_id', $book->user()->getForeignKeyName());
        $this->assertInstanceOf(User::class, $book->user);
        $this->assertTrue($book->user->is($user));
    }

    /**
     * M6 Book は複数のジャンルを持つ（genres）
     */
    public function test_m6_book_belongs_to_many_genres(): void
    {
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();
        $otherGenre = Genre::factory()->create();
        $book->genres()->attach($genres->pluck('id'));

        $this->assertInstanceOf(BelongsToMany::class, $book->genres());
        $this->assertSame('book_genre', $book->genres()->getTable());
        $this->assertCount(2, $book->genres);
        $this->assertEqualsCanonicalizing($genres->pluck('id')->all(), $book->genres->pluck('id')->all());
        $this->assertFalse($book->genres->contains($otherGenre));
        // 中間テーブルに created_at が記録される（withTimestamps）
        $this->assertNotNull($book->genres->first()->pivot->created_at);
    }

    /**
     * M7 Book は複数のレビューを持つ（reviews）
     */
    public function test_m7_book_has_many_reviews(): void
    {
        $book = Book::factory()->create();
        $reviews = Review::factory()->count(3)->for($book)->create();
        $otherBooksReview = Review::factory()->create();

        $this->assertInstanceOf(HasMany::class, $book->reviews());
        $this->assertSame('book_id', $book->reviews()->getForeignKeyName());
        $this->assertCount(3, $book->reviews);
        $this->assertEqualsCanonicalizing($reviews->pluck('id')->all(), $book->reviews->pluck('id')->all());
        $this->assertFalse($book->reviews->contains($otherBooksReview));
    }
}
