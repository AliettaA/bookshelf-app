<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ランキングのテスト（ケースID K1〜K7）。
 */
class RankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * K1【表示】平均評価の高い順に表示される
     */
    public function test_k1_ranking_is_ordered_by_average_rating_desc(): void
    {
        // 登録順（id順）と平均評価の順が一致しないように作る
        $middleBook = Book::factory()->create(['title' => '平均4.5の書籍']);
        $this->createReviews($middleBook, [4, 5]);
        $lowestBook = Book::factory()->create(['title' => '平均3.0の書籍']);
        $this->createReviews($lowestBook, [3]);
        $highestBook = Book::factory()->create(['title' => '平均5.0の書籍']);
        $this->createReviews($highestBook, [5]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSeeInOrder(['平均5.0の書籍', '平均4.5の書籍', '平均3.0の書籍']);
    }

    /**
     * K2【表示】最大10件まで表示される
     */
    public function test_k2_ranking_shows_up_to_10_books(): void
    {
        foreach (range(1, 10) as $number) {
            $book = Book::factory()->create(['title' => sprintf('ランキング上位書籍%02d', $number)]);
            $this->createReviews($book, [5]);
        }
        // 平均評価が最も低いので、11位になる書籍
        $eleventhBook = Book::factory()->create(['title' => 'ランキング11位の書籍']);
        $this->createReviews($eleventhBook, [1]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk()
            ->assertSee('ランキング上位書籍01')
            ->assertSee('ランキング上位書籍10')
            ->assertDontSee('ランキング11位の書籍');
        $this->assertCount(10, $response->viewData('rankedBooks'));
    }

    /**
     * K3【表示】レビューがない書籍は表示されない
     */
    public function test_k3_books_without_reviews_are_not_shown(): void
    {
        $reviewedBook = Book::factory()->create(['title' => 'レビューありの書籍']);
        $this->createReviews($reviewedBook, [4]);
        Book::factory()->create(['title' => 'レビューなしの書籍']);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('レビューありの書籍')
            ->assertDontSee('レビューなしの書籍');
    }

    /**
     * K4【並び順】平均評価が同じ場合はレビュー件数の多い順に表示される
     */
    public function test_k4_same_average_is_ordered_by_reviews_count_desc(): void
    {
        // いずれも平均4.0。id が大きい（後から登録した）書籍ほど件数が少なくなるように作る
        $threeReviewsBook = Book::factory()->create(['title' => 'レビュー3件の書籍']);
        $this->createReviews($threeReviewsBook, [4, 4, 4]);
        $twoReviewsBook = Book::factory()->create(['title' => 'レビュー2件の書籍']);
        $this->createReviews($twoReviewsBook, [3, 5]);
        $oneReviewBook = Book::factory()->create(['title' => 'レビュー1件の書籍']);
        $this->createReviews($oneReviewBook, [4]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSeeInOrder(['レビュー3件の書籍', 'レビュー2件の書籍', 'レビュー1件の書籍']);
    }

    /**
     * K5【表示】レビューのある書籍がない場合はメッセージが表示される
     */
    public function test_k5_shows_message_when_no_reviewed_books(): void
    {
        // 書籍はあるが、レビューのある書籍が1冊もない場合にメッセージが表示される
        Book::factory()->create();

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('まだレビューが投稿された書籍がありません。');
    }

    /**
     * K6【表示】平均評価とレビュー件数が表示される
     */
    public function test_k6_ranking_shows_average_rating_and_reviews_count(): void
    {
        $book = Book::factory()->create(['title' => '平均評価確認用の書籍']);
        $this->createReviews($book, [4, 5]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('平均評価確認用の書籍')
            ->assertSee('4.50')
            ->assertSee('4.5')
            ->assertSee('2件のレビュー');
    }

    /**
     * K7【並び順】平均評価もレビュー件数も同じ場合は、後から登録した書籍が先に表示される
     */
    public function test_k7_same_average_and_count_shows_newer_book_first(): void
    {
        $olderBook = Book::factory()->create(['title' => '先に登録した書籍']);
        $this->createReviews($olderBook, [4, 4]);
        $newerBook = Book::factory()->create(['title' => '後から登録した書籍']);
        $this->createReviews($newerBook, [4, 4]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSeeInOrder(['後から登録した書籍', '先に登録した書籍']);
    }

    /**
     * 指定した評価の数だけ、書籍にレビューを作る。
     *
     * @param  array<int, int>  $ratings
     */
    private function createReviews(Book $book, array $ratings): void
    {
        foreach ($ratings as $rating) {
            Review::factory()->for($book)->create(['rating' => $rating]);
        }
    }
}
