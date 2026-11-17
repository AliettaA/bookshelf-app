<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 画面アクセスのテスト（ケースID P1〜P7）。
 */
class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * P1【アクセス】ゲストが公開ページを表示できる
     */
    public function test_p1_guest_can_view_public_pages(): void
    {
        $book = Book::factory()->create();

        $publicUrls = [
            '/',
            '/books',
            route('books.show', $book, false),
            '/ranking',
            '/login',
            '/register',
        ];

        foreach ($publicUrls as $url) {
            $this->get($url)->assertOk();
        }
    }

    /**
     * P2【権限】ゲストが認証必須ページにアクセスすると /login へリダイレクトされる
     */
    public function test_p2_guest_is_redirected_to_login_from_auth_required_pages(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $review = Review::factory()->for($book)->create();

        $authRequiredUrls = [
            route('books.create', [], false),
            route('books.edit', $book, false),
            route('genres.index', [], false),
            route('genres.show', $genre, false),
            route('genres.create', [], false),
            route('genres.edit', $genre, false),
            route('reviews.edit', $review, false),
            route('favorites.index', [], false),
        ];
        $this->assertCount(8, $authRequiredUrls);

        foreach ($authRequiredUrls as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    /**
     * P3【アクセス】ログインユーザーが認証必須ページを表示できる
     */
    public function test_p3_logged_in_user_can_view_auth_required_pages(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user);

        $authRequiredUrls = [
            route('books.create', [], false),
            route('genres.index', [], false),
            route('genres.show', $genre, false),
            route('genres.create', [], false),
            route('genres.edit', $genre, false),
            route('favorites.index', [], false),
        ];

        foreach ($authRequiredUrls as $url) {
            $this->get($url)->assertOk();
        }
    }

    /**
     * P4【権限】書籍編集画面は作成者のみ表示できる
     */
    public function test_p4_book_edit_page_is_viewable_only_by_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('books.edit', $book))->assertOk();
        $this->actingAs($otherUser)->get(route('books.edit', $book))->assertForbidden();
    }

    /**
     * P5【権限】レビュー編集画面は投稿者のみ表示できる
     */
    public function test_p5_review_edit_page_is_viewable_only_by_review_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('reviews.edit', $review))->assertOk();
        $this->actingAs($otherUser)->get(route('reviews.edit', $review))->assertForbidden();
    }

    /**
     * P6【データ】各画面に、Bladeが必要とするデータが渡される（CLAUDE.md §7 の表）
     */
    public function test_p6_each_view_receives_the_data_blade_needs(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($user)->create();
        $book->genres()->attach($genre->id);
        $review = Review::factory()->for($book)->for($user)->create();
        $user->favoriteBooks()->attach($book->id);

        $this->actingAs($user);

        // books.index：$books（/ も同じ画面）
        foreach (['/', route('books.index')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertViewIs('books.index')
                ->assertViewHas('books')
                ->assertViewHas('books', fn ($books) => $books->first()->relationLoaded('genres')
                    && array_key_exists('reviews_avg_rating', $books->first()->getAttributes()));
        }

        // books.show：$book（genres・reviews.user・reviews.likedByUsers を読み込み済み）
        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertViewHas('book', fn ($viewBook) => $viewBook->is($book)
                && $viewBook->relationLoaded('genres')
                && $viewBook->relationLoaded('reviews')
                && $viewBook->reviews->first()->relationLoaded('user')
                && $viewBook->reviews->first()->relationLoaded('likedByUsers'));

        // books.create：$genres。$book は渡さない（Blade が isset($book) で登録/編集を判定するため）
        $this->get(route('books.create'))
            ->assertOk()
            ->assertViewIs('books.create')
            ->assertViewHas('genres')
            ->assertViewMissing('book');

        // books.edit：$book, $genres
        $this->get(route('books.edit', $book))
            ->assertOk()
            ->assertViewIs('books.edit')
            ->assertViewHas('book', fn ($viewBook) => $viewBook->is($book))
            ->assertViewHas('genres');

        // reviews.edit：$review（$review->book を読み込み済み）
        $this->get(route('reviews.edit', $review))
            ->assertOk()
            ->assertViewIs('reviews.edit')
            ->assertViewHas('review', fn ($viewReview) => $viewReview->is($review)
                && $viewReview->relationLoaded('book'));

        // genres.index：$genres（books_count 付き）
        $this->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index')
            ->assertViewHas('genres', fn ($genres) => $genres->first()->books_count === 1);

        // genres.show：$genre, $books
        $this->get(route('genres.show', $genre))
            ->assertOk()
            ->assertViewIs('genres.show')
            ->assertViewHas('genre', fn ($viewGenre) => $viewGenre->is($genre))
            ->assertViewHas('books', fn ($books) => $books->first()->is($book));

        // genres.create：渡す変数なし
        $this->get(route('genres.create'))
            ->assertOk()
            ->assertViewIs('genres.create');

        // genres.edit：$genre
        $this->get(route('genres.edit', $genre))
            ->assertOk()
            ->assertViewIs('genres.edit')
            ->assertViewHas('genre', fn ($viewGenre) => $viewGenre->is($genre));

        // favorites.index：$books
        $this->get(route('favorites.index'))
            ->assertOk()
            ->assertViewIs('favorites.index')
            ->assertViewHas('books', fn ($books) => $books->first()->is($book));

        // ranking.index：$rankedBooks（平均評価とレビュー件数付き）
        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertViewIs('ranking.index')
            ->assertViewHas('rankedBooks', fn ($rankedBooks) => $rankedBooks->first()->is($book)
                && $rankedBooks->first()->reviews_count === 1
                && $rankedBooks->first()->reviews_avg_rating !== null);
    }

    /**
     * P7【異常系】存在しないデータの画面は 404 になる
     */
    public function test_p7_nonexistent_data_pages_return_404(): void
    {
        $this->actingAs(User::factory()->create());

        $notFoundUrls = [
            '/books/9999',
            '/books/9999/edit',
            '/genres/9999',
            '/reviews/9999/edit',
        ];

        foreach ($notFoundUrls as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
