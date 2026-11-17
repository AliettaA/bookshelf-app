<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Genre モデルのリレーションのテスト（ケースID M8）。
 */
class GenreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * M8 Genre は複数の書籍を持つ（books）
     */
    public function test_m8_genre_belongs_to_many_books(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(2)->create();
        $otherBook = Book::factory()->create();
        $genre->books()->attach($books->pluck('id'));

        $this->assertInstanceOf(BelongsToMany::class, $genre->books());
        $this->assertSame('book_genre', $genre->books()->getTable());
        $this->assertCount(2, $genre->books);
        $this->assertEqualsCanonicalizing($books->pluck('id')->all(), $genre->books->pluck('id')->all());
        $this->assertFalse($genre->books->contains($otherBook));
        // 中間テーブルに created_at が記録される（withTimestamps）
        $this->assertNotNull($genre->books->first()->pivot->created_at);
    }
}
