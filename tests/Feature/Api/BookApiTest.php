<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 公開API（書籍）のテスト（ケースID X1〜X12）。
 */
class BookApiTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 一覧
    // ------------------------------------------------------------------

    /**
     * X1【一覧】書籍一覧を取得できる
     */
    public function test_x1_index_returns_books_with_pagination_structure(): void
    {
        $genre = Genre::factory()->create(['name' => '小説']);
        $book = Book::factory()->create();
        $book->genres()->attach($genre->id);
        Review::factory()->for($book)->create(['rating' => 4]);

        $this->getJson(route('api.v1.books.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'author',
                        'isbn',
                        'published_date',
                        'description',
                        'image_url',
                        'average_rating',
                        'reviews_count',
                        'genres' => [['id', 'name']],
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.id', $book->id)
            ->assertJsonPath('data.0.genres.0.name', '小説')
            ->assertJsonPath('data.0.average_rating', 4)
            ->assertJsonPath('data.0.reviews_count', 1);
    }

    /**
     * X2【一覧】デフォルト20件、per_page で件数を変えられる
     */
    public function test_x2_index_defaults_to_20_per_page_and_accepts_per_page(): void
    {
        Book::factory()->count(21)->create();

        $this->getJson(route('api.v1.books.index'))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 21);

        $this->getJson(route('api.v1.books.index', ['per_page' => 5]))
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5);
    }

    /**
     * X3【一覧】新しい順で返される
     */
    public function test_x3_index_returns_newest_first(): void
    {
        // 登録順（id順）と作成日時の順が一致しないように作る
        Book::factory()->create(['title' => '真ん中の書籍', 'created_at' => now()->subDays(2)]);
        Book::factory()->create(['title' => '一番古い書籍', 'created_at' => now()->subDays(3)]);
        Book::factory()->create(['title' => '一番新しい書籍', 'created_at' => now()->subDay()]);

        $response = $this->getJson(route('api.v1.books.index'))->assertOk();

        $this->assertSame(
            ['一番新しい書籍', '真ん中の書籍', '一番古い書籍'],
            $response->json('data.*.title'),
        );
    }

    /**
     * X4【一覧】keyword でタイトル・著者のどちらでも部分一致検索できる
     */
    public function test_x4_index_searches_title_and_author_by_keyword(): void
    {
        $titleBook = Book::factory()->create(['title' => '吾輩は猫である', 'author' => '著者A']);
        $authorBook = Book::factory()->create(['title' => '別の書籍', 'author' => '夏目 漱石']);
        Book::factory()->create(['title' => '無関係の書籍', 'author' => '著者C']);

        // タイトルのみに含まれる語
        $this->getJson(route('api.v1.books.index', ['keyword' => '吾輩']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $titleBook->id);

        // 著者のみに含まれる語
        $this->getJson(route('api.v1.books.index', ['keyword' => '漱石']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $authorBook->id);

        // どれにも一致しない語
        $this->getJson(route('api.v1.books.index', ['keyword' => '存在しない語']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * X5【一覧】genre_id で絞り込める
     */
    public function test_x5_index_filters_by_genre_id(): void
    {
        $novel = Genre::factory()->create();
        $cooking = Genre::factory()->create();
        $novelBook = Book::factory()->create();
        $novelBook->genres()->attach($novel->id);
        $cookingBook = Book::factory()->create();
        $cookingBook->genres()->attach($cooking->id);
        Book::factory()->create(); // ジャンルなし

        $response = $this->getJson(route('api.v1.books.index', ['genre_id' => $novel->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $novelBook->id);

        $this->assertFalse(in_array($cookingBook->id, $response->json('data.*.id'), true));
    }

    /**
     * X6【一覧】keyword と genre_id を組み合わせて絞り込める
     */
    public function test_x6_index_combines_keyword_and_genre_id_with_and(): void
    {
        $targetGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();

        // 両方の条件を満たす（タイトルに一致・著者に一致）
        $titleMatch = Book::factory()->create(['title' => '共通キーワードの本', 'author' => '著者A']);
        $authorMatch = Book::factory()->create(['title' => '別の本', 'author' => '共通キーワード 太郎']);
        $titleMatch->genres()->attach($targetGenre->id);
        $authorMatch->genres()->attach($targetGenre->id);

        // ジャンルだけ一致（キーワードなし）
        $genreOnly = Book::factory()->create(['title' => 'ジャンルだけ一致', 'author' => '著者C']);
        $genreOnly->genres()->attach($targetGenre->id);

        // キーワードだけ一致（別ジャンル）
        $keywordOnly = Book::factory()->create(['title' => '共通キーワードだけ一致', 'author' => '著者D']);
        $keywordOnly->genres()->attach($otherGenre->id);

        $response = $this->getJson(route('api.v1.books.index', [
            'keyword' => '共通キーワード',
            'genre_id' => $targetGenre->id,
        ]))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$titleMatch->id, $authorMatch->id],
            $response->json('data.*.id'),
        );
    }

    /**
     * X7【一覧】検索パラメータが不正だと 422
     *
     * @param  array<string, int>  $query
     */
    #[DataProvider('invalidIndexQueryProvider')]
    public function test_x7_index_rejects_invalid_query(array $query, string $field, string $message): void
    {
        $this->getJson(route('api.v1.books.index', $query))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field => $message]);
    }

    /**
     * @return array<string, array{0: array<string, int>, 1: string, 2: string}>
     */
    public static function invalidIndexQueryProvider(): array
    {
        return [
            'per_page=0' => [['per_page' => 0], 'per_page', '1ページあたりの件数は1〜100の範囲で指定してください。'],
            'per_page=101' => [['per_page' => 101], 'per_page', '1ページあたりの件数は1〜100の範囲で指定してください。'],
            'page=0' => [['page' => 0], 'page', 'ページ番号は1以上の整数で指定してください。'],
            '存在しないgenre_id' => [['genre_id' => 9999], 'genre_id', '指定されたジャンルが存在しません。'],
        ];
    }

    /**
     * X8【一覧】平均評価は小数第1位、レビューがない書籍は null
     */
    public function test_x8_average_rating_is_rounded_to_one_decimal_and_null_without_reviews(): void
    {
        $reviewedBook = Book::factory()->create(['created_at' => now()->subDay()]);
        foreach ([4, 4, 5] as $rating) {
            Review::factory()->for($reviewedBook)->create(['rating' => $rating]);
        }
        $noReviewBook = Book::factory()->create(['created_at' => now()->subDays(2)]);

        $response = $this->getJson(route('api.v1.books.index'))->assertOk();

        // 平均 4.333... は 4.3 になる
        $response->assertJsonPath('data.0.id', $reviewedBook->id)
            ->assertJsonPath('data.0.average_rating', 4.3)
            ->assertJsonPath('data.0.reviews_count', 3);
        // レビューなしは null（0 ではない）・件数は 0
        $response->assertJsonPath('data.1.id', $noReviewBook->id)
            ->assertJsonPath('data.1.average_rating', null)
            ->assertJsonPath('data.1.reviews_count', 0);
    }

    // ------------------------------------------------------------------
    // 詳細
    // ------------------------------------------------------------------

    /**
     * X9【詳細】書籍詳細を取得できる
     */
    public function test_x9_show_returns_book_with_genres_and_reviews_newest_first(): void
    {
        $genre = Genre::factory()->create(['name' => '技術書']);
        $book = Book::factory()->create(['title' => '詳細取得テストの本']);
        $book->genres()->attach($genre->id);
        Review::factory()->for($book)->for(User::factory()->create(['name' => '古い投稿者']))->create([
            'rating' => 3,
            'comment' => '古いレビュー',
            'created_at' => now()->subDays(2),
        ]);
        Review::factory()->for($book)->for(User::factory()->create(['name' => '新しい投稿者']))->create([
            'rating' => 5,
            'comment' => '新しいレビュー',
            'created_at' => now()->subDay(),
        ]);

        $this->getJson(route('api.v1.books.show', $book))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'author',
                    'isbn',
                    'published_date',
                    'description',
                    'image_url',
                    'average_rating',
                    'reviews_count',
                    'genres' => [['id', 'name']],
                    'reviews' => [['id', 'rating', 'comment', 'user_name', 'created_at']],
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '詳細取得テストの本')
            ->assertJsonPath('data.genres.0.name', '技術書')
            ->assertJsonPath('data.average_rating', 4)
            ->assertJsonPath('data.reviews_count', 2)
            ->assertJsonCount(2, 'data.reviews')
            // レビューは新しい順
            ->assertJsonPath('data.reviews.0.comment', '新しいレビュー')
            ->assertJsonPath('data.reviews.0.user_name', '新しい投稿者')
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath('data.reviews.1.comment', '古いレビュー')
            ->assertJsonPath('data.reviews.1.user_name', '古い投稿者');
    }

    /**
     * X10【詳細】存在しない書籍は 404 と日本語メッセージ
     */
    public function test_x10_show_returns_404_with_japanese_message_for_nonexistent_book(): void
    {
        $this->getJson('/api/v1/books/9999')
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりませんでした。']);
    }

    /**
     * X11【一覧・詳細】登録者の情報がレスポンスに含まれない
     */
    public function test_x11_responses_do_not_include_user_information(): void
    {
        $owner = User::factory()->create(['name' => '登録者の名前', 'email' => 'owner@example.com']);
        $book = Book::factory()->for($owner)->create();
        Review::factory()->for($book)->create();

        $index = $this->getJson(route('api.v1.books.index'))->assertOk();
        $index->assertJsonMissingPath('data.0.user')
            ->assertJsonMissingPath('data.0.user_id');

        $show = $this->getJson(route('api.v1.books.show', $book))->assertOk();
        $show->assertJsonMissingPath('data.user')
            ->assertJsonMissingPath('data.user_id')
            // レビュー投稿者は名前（user_name）のみ。ID・メールアドレスは含まれない
            ->assertJsonMissingPath('data.reviews.0.user')
            ->assertJsonMissingPath('data.reviews.0.user_id')
            ->assertJsonMissingPath('data.reviews.0.email');

        $this->assertStringNotContainsString('owner@example.com', $index->getContent());
        $this->assertStringNotContainsString('owner@example.com', $show->getContent());
    }

    // ------------------------------------------------------------------
    // 登録
    // ------------------------------------------------------------------

    /**
     * X12【登録】書籍を登録できる
     */
    public function test_x12_store_creates_book_with_genres(): void
    {
        $user = User::factory()->create();
        $novel = Genre::factory()->create(['name' => '小説']);
        $history = Genre::factory()->create(['name' => '歴史']);

        $response = $this->postJson(route('api.v1.books.store'), [
            'user_id' => $user->id,
            'title' => 'API登録テストの本',
            'author' => '登録 太郎',
            'isbn' => '9784000000001',
            'published_date' => '2024-05-01',
            'description' => 'API登録テストの説明です。',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => [$novel->id, $history->id],
        ]);

        $book = Book::where('isbn', '9784000000001')->firstOrFail();
        $response->assertCreated()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', 'API登録テストの本')
            ->assertJsonCount(2, 'data.genres')
            ->assertJsonMissingPath('data.user');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => 'API登録テストの本',
            'author' => '登録 太郎',
            'published_date' => '2024-05-01',
            'description' => 'API登録テストの説明です。',
            'image_url' => 'https://example.com/image.jpg',
        ]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $novel->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $history->id]);
        $this->assertDatabaseCount('books', 1);
    }

    /**
     * X13【登録】バリデーションエラーは 422 と日本語メッセージ
     */
    public function test_x13_store_returns_422_with_japanese_messages(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Book::factory()->create(['isbn' => '9784000000001']);

        // 必須項目が空
        $this->postJson(route('api.v1.books.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'user_id' => '登録者IDは必須です。',
                'title' => 'タイトルは必須です。',
                'author' => '著者は必須です。',
                'isbn' => 'ISBNは必須です。',
                'published_date' => '出版日は必須です。',
                'genres' => 'ジャンルは1つ以上選択してください。',
            ]);

        // ISBN が重複
        $this->postJson(route('api.v1.books.store'), $this->validApiBookData([
            'user_id' => $user->id,
            'isbn' => '9784000000001',
            'genres' => [$genre->id],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isbn' => 'そのISBNは既に使用されています。']);

        // 存在しない user_id
        $this->postJson(route('api.v1.books.store'), $this->validApiBookData([
            'user_id' => 9999,
            'genres' => [$genre->id],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id' => '指定された登録者は存在しません。']);

        // どのケースでも登録されていない（最初に作った1件のまま）
        $this->assertDatabaseCount('books', 1);
    }

    /**
     * X14【登録】Accept ヘッダーがなくても、エラーは 422 のJSONで返る
     */
    public function test_x14_store_returns_422_json_without_accept_header(): void
    {
        // postJson ではなく post で送る（Accept: application/json を付けない）
        $response = $this->post(route('api.v1.books.store'), []);

        // 302（リダイレクト）ではなく 422 の JSON になる
        $response->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors(['title' => 'タイトルは必須です。']);

        $this->assertDatabaseCount('books', 0);
    }

    // ------------------------------------------------------------------
    // 更新
    // ------------------------------------------------------------------

    /**
     * X15【更新】書籍を更新できる（自分自身のISBNはそのまま使える）
     */
    public function test_x15_update_changes_book_and_allows_own_isbn(): void
    {
        $user = User::factory()->create();
        $oldGenre = Genre::factory()->create();
        $newGenre = Genre::factory()->create(['name' => '更新後のジャンル']);
        $book = Book::factory()->create(['isbn' => '9784000000001', 'title' => '更新前のタイトル']);
        $book->genres()->attach($oldGenre->id);

        $this->putJson(route('api.v1.books.update', $book), $this->validApiBookData([
            'user_id' => $user->id,
            'title' => '更新後のタイトル',
            'isbn' => '9784000000001', // 自分自身のISBNのまま
            'genres' => [$newGenre->id],
        ]))
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '更新後のタイトル')
            ->assertJsonPath('data.genres.0.name', '更新後のジャンル')
            ->assertJsonCount(1, 'data.genres');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => '更新後のタイトル',
            'isbn' => '9784000000001',
        ]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $newGenre->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenre->id]);
        $this->assertDatabaseCount('books', 1);
    }

    /**
     * X16【更新】他の書籍のISBNには更新できない
     */
    public function test_x16_update_rejects_isbn_of_another_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['isbn' => '9784000000001', 'title' => '更新前のタイトル']);
        Book::factory()->create(['isbn' => '9784000000002']);

        $this->putJson(route('api.v1.books.update', $book), $this->validApiBookData([
            'user_id' => $user->id,
            'title' => '更新後のタイトル',
            'isbn' => '9784000000002', // 他の書籍のISBN
            'genres' => [$genre->id],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isbn' => 'そのISBNは既に使用されています。']);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新前のタイトル',
            'isbn' => '9784000000001',
        ]);
        $this->assertDatabaseCount('book_genre', 0);
    }

    /**
     * X17【更新】存在しない書籍の更新は 404
     */
    public function test_x17_update_returns_404_for_nonexistent_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->putJson('/api/v1/books/9999', $this->validApiBookData([
            'user_id' => $user->id,
            'genres' => [$genre->id],
        ]))
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりませんでした。']);

        $this->assertDatabaseCount('books', 0);
    }

    // ------------------------------------------------------------------
    // 削除
    // ------------------------------------------------------------------

    /**
     * X18【削除】書籍を削除でき、関連データも消える
     */
    public function test_x18_destroy_deletes_book_and_related_data(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genre->id);
        $review = Review::factory()->for($book)->create();
        $review->likedByUsers()->attach($user->id);
        $user->favoriteBooks()->attach($book->id);

        // 削除されない別の書籍のデータ
        $otherBook = Book::factory()->create();
        $otherReview = Review::factory()->for($otherBook)->create();

        $response = $this->deleteJson(route('api.v1.books.destroy', $book));

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);

        // ジャンル・ユーザー・他の書籍とそのレビューは残る
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('books', ['id' => $otherBook->id]);
        $this->assertDatabaseHas('reviews', ['id' => $otherReview->id]);
    }

    /**
     * X19【削除】存在しない書籍の削除は 404
     */
    public function test_x19_destroy_returns_404_for_nonexistent_book(): void
    {
        $existingBook = Book::factory()->create();

        $this->deleteJson('/api/v1/books/9999')
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりませんでした。']);

        $this->assertDatabaseHas('books', ['id' => $existingBook->id]);
    }

    /**
     * 登録・更新APIのリクエストデータ（有効な値）を返す。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validApiBookData(array $overrides = []): array
    {
        return [
            'user_id' => User::factory()->create()->id,
            'title' => 'APIテストの本',
            'author' => 'API 太郎',
            'isbn' => '9784000000099',
            'published_date' => '2024-05-01',
            'description' => 'APIテストの説明です。',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => [],
            ...$overrides,
        ];
    }
}
