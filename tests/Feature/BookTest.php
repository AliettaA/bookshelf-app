<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 書籍CRUDのテスト（ケースID B1〜B23）。
 */
class BookTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 一覧
    // ------------------------------------------------------------------

    /**
     * B1【一覧】10件ずつ・新しい順で表示される
     */
    public function test_b1_index_shows_10_per_page_newest_first(): void
    {
        // created_at を1日ずつずらして11件作る（番号が大きいほど新しい）
        foreach (range(1, 11) as $number) {
            Book::factory()->create([
                'title' => sprintf('一覧テスト書籍%02d', $number),
                'created_at' => now()->subDays(12 - $number),
            ]);
        }

        $firstPage = $this->get(route('books.index'));

        $firstPage->assertOk();
        $titles = $firstPage->viewData('books')->pluck('title')->all();
        $this->assertCount(10, $titles);
        $this->assertSame('一覧テスト書籍11', $titles[0]);
        $this->assertSame('一覧テスト書籍02', $titles[9]);
        $firstPage->assertDontSee('一覧テスト書籍01');

        $secondPage = $this->get(route('books.index', ['page' => 2]));

        $secondPage->assertOk();
        $this->assertSame(['一覧テスト書籍01'], $secondPage->viewData('books')->pluck('title')->all());
    }

    /**
     * B2【一覧】各書籍のジャンルが表示される
     */
    public function test_b2_index_shows_genres_of_each_book(): void
    {
        $novel = Genre::factory()->create(['name' => '小説']);
        $history = Genre::factory()->create(['name' => '歴史']);
        Genre::factory()->create(['name' => '料理']); // どの書籍にも付いていないジャンル
        $book = Book::factory()->create();
        $book->genres()->sync([$novel->id, $history->id]);

        $response = $this->get(route('books.index'));

        $response->assertOk()
            ->assertSee('小説')
            ->assertSee('歴史')
            ->assertDontSee('料理');
    }

    /**
     * B3【一覧】レビューのある書籍は平均評価が表示され、ない書籍は表示されない
     */
    public function test_b3_index_shows_average_rating_only_for_reviewed_books(): void
    {
        $reviewedBook = Book::factory()->create(['title' => 'レビューあり書籍']);
        Review::factory()->for($reviewedBook)->create(['rating' => 4]);
        Review::factory()->for($reviewedBook)->create(['rating' => 5]);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('レビューあり書籍')
            ->assertSee('(4.5)');

        // レビューのない書籍だけの画面では、平均も星も出ない
        Review::query()->delete();

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('レビューあり書籍')
            ->assertDontSee('(4.5)')
            ->assertDontSee('★')
            ->assertDontSee('☆');
    }

    /**
     * B4【一覧】書籍が0件のときはメッセージが表示される
     */
    public function test_b4_index_shows_message_when_no_books(): void
    {
        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('書籍が登録されていません。');
    }

    // ------------------------------------------------------------------
    // 詳細
    // ------------------------------------------------------------------

    /**
     * B5【詳細】書籍情報が表示される
     */
    public function test_b5_show_displays_book_information(): void
    {
        $genre = Genre::factory()->create(['name' => '技術書']);
        $book = Book::factory()->create([
            'title' => '詳細表示テストの本',
            'author' => '詳細 太郎',
            'isbn' => '9784000000001',
            'published_date' => '2024-03-15',
            'description' => 'これは詳細画面に表示される説明文です。',
        ]);
        $book->genres()->sync([$genre->id]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('詳細表示テストの本')
            ->assertSee('詳細 太郎')
            ->assertSee('9784000000001')
            ->assertSee('2024-03-15')
            ->assertSee('これは詳細画面に表示される説明文です。')
            ->assertSee('技術書');
    }

    /**
     * B6【詳細】編集・削除ボタンは作成者にだけ表示される
     */
    public function test_b6_show_displays_edit_and_delete_only_to_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->for($owner)->create();

        $editLink = 'href="'.route('books.edit', $book).'"';
        $deleteForm = '<form action="'.route('books.destroy', $book).'"';
        $deleteMethod = 'name="_method" value="DELETE"';

        // ゲストには表示されない
        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertDontSee($editLink, false)
            ->assertDontSee($deleteForm, false)
            ->assertDontSee($deleteMethod, false);

        // 他のユーザーには表示されない
        $this->actingAs($otherUser)->get(route('books.show', $book))
            ->assertOk()
            ->assertDontSee($editLink, false)
            ->assertDontSee($deleteForm, false)
            ->assertDontSee($deleteMethod, false);

        // 作成者には表示される
        $this->actingAs($owner)->get(route('books.show', $book))
            ->assertOk()
            ->assertSee($editLink, false)
            ->assertSee($deleteForm, false)
            ->assertSee($deleteMethod, false);
    }

    // ------------------------------------------------------------------
    // 登録
    // ------------------------------------------------------------------

    /**
     * B7【登録】登録画面に全ジャンルがチェックボックスで表示される
     */
    public function test_b7_create_form_lists_all_genres_as_checkboxes(): void
    {
        Genre::factory()->create(['name' => '小説']);
        Genre::factory()->create(['name' => 'ビジネス']);
        Genre::factory()->create(['name' => '旅行']);

        $this->actingAs(User::factory()->create())
            ->get(route('books.create'))
            ->assertOk()
            ->assertSee('小説')
            ->assertSee('ビジネス')
            ->assertSee('旅行')
            ->assertSee('name="genres[]"', false);
    }

    /**
     * B8【登録】ジャンルが0件のとき、登録画面にメッセージが表示される
     */
    public function test_b8_create_form_shows_message_when_no_genres(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('books.create'))
            ->assertOk()
            ->assertSee('ジャンルが登録されていません。先にジャンルを登録してください。')
            ->assertDontSee('name="genres[]"', false);
    }

    /**
     * B9【登録】書籍を登録できる
     */
    public function test_b9_user_can_store_book(): void
    {
        $user = User::factory()->create();
        $novel = Genre::factory()->create(['name' => '小説']);
        $history = Genre::factory()->create(['name' => '歴史']);

        $response = $this->actingAs($user)->post(route('books.store'), $this->validBookData([
            'genres' => [$novel->id, $history->id],
        ]));

        $book = Book::where('isbn', '9784000000001')->firstOrFail();
        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を登録しました。');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => '登録テストの本',
            'author' => '登録 太郎',
            'published_date' => '2024-05-01',
            'description' => '登録テストの説明です。',
            'image_url' => 'https://example.com/image.jpg',
        ]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $novel->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $history->id]);

        $this->get(route('books.show', $book))->assertSee('書籍を登録しました。');
    }

    /**
     * B10【登録】必須項目が空だと登録できない
     */
    public function test_b10_store_requires_required_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), [])
            ->assertSessionHasErrors([
                'title' => 'タイトルは必須です。',
                'author' => '著者は必須です。',
                'isbn' => 'ISBNは必須です。',
                'published_date' => '出版日は必須です。',
                'genres' => 'ジャンルは1つ以上選択してください。',
            ]);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * B11【登録】ISBNが半角数字13桁でないと登録できない
     */
    #[DataProvider('invalidIsbnProvider')]
    public function test_b11_store_rejects_isbn_that_is_not_13_halfwidth_digits(string $isbn): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validBookData(['isbn' => $isbn, 'genres' => [$genre->id]]))
            ->assertSessionHasErrors(['isbn' => 'ISBNは13桁で入力してください。']);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidIsbnProvider(): array
    {
        return [
            '12桁' => ['978400000000'],
            '14桁' => ['97840000000012'],
            'ハイフン入り' => ['978-4-00-000000-0'],
            '英字' => ['abcdefghijklm'],
            '全角数字' => ['９７８４０００００００００'],
        ];
    }

    /**
     * B12【登録】ISBNが重複すると登録できない
     */
    public function test_b12_store_rejects_duplicate_isbn(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->create(['isbn' => '9784000000001']);

        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validBookData(['isbn' => '9784000000001', 'genres' => [$genre->id]]))
            ->assertSessionHasErrors(['isbn' => 'そのISBNは既に使用されています。']);

        $this->assertDatabaseCount('books', 1);
    }

    /**
     * B13【登録】画像URL・出版日・ジャンルが不正だと登録できない
     *
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidBookFieldProvider')]
    public function test_b13_store_rejects_invalid_image_url_published_date_and_genre(array $overrides, string $errorKey, string $message): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validBookData(['genres' => [$genre->id], ...$overrides]))
            ->assertSessionHasErrors([$errorKey => $message]);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidBookFieldProvider(): array
    {
        return [
            '画像URLがURL形式でない' => [
                ['image_url' => 'url-ではない'],
                'image_url',
                '画像URLは有効なURL形式で入力してください。',
            ],
            '出版日が日付でない' => [
                ['published_date' => '日付ではない'],
                'published_date',
                '出版日は有効な日付形式で入力してください。',
            ],
            '存在しないジャンルID' => [
                ['genres' => [999999]],
                'genres.0',
                '選択されたジャンルは存在しません。',
            ],
        ];
    }

    /**
     * B14【登録】説明・画像URLは空でも登録できる
     */
    public function test_b14_store_allows_empty_description_and_image_url(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validBookData([
                'genres' => [$genre->id],
                'description' => '',
                'image_url' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', [
            'isbn' => '9784000000001',
            'description' => null,
            'image_url' => null,
        ]);
    }

    /**
     * B14【登録】説明は長文でも登録できる（文字数上限がない）
     */
    public function test_b14_store_allows_long_description(): void
    {
        $genre = Genre::factory()->create();
        $longDescription = str_repeat('長い説明文。', 2000); // 12,000文字

        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validBookData([
                'genres' => [$genre->id],
                'description' => $longDescription,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($longDescription, Book::where('isbn', '9784000000001')->firstOrFail()->description);
    }

    /**
     * B15【登録】エラー時は入力内容とジャンルのチェックが保持される
     */
    public function test_b15_store_keeps_old_input_and_checked_genres_on_error(): void
    {
        $user = User::factory()->create();
        $novel = Genre::factory()->create(['name' => '小説']);
        $business = Genre::factory()->create(['name' => 'ビジネス']);
        $travel = Genre::factory()->create(['name' => '旅行']);

        // タイトルだけ空にしてエラーにする
        $response = $this->actingAs($user)
            ->from(route('books.create'))
            ->post(route('books.store'), $this->validBookData([
                'title' => '',
                'author' => '保持される著者',
                'genres' => [$novel->id, $travel->id],
            ]));

        $response->assertRedirect(route('books.create'))
            ->assertSessionHasErrors(['title'])
            ->assertSessionHasInput('author', '保持される著者')
            ->assertSessionHasInput('isbn', '9784000000001')
            ->assertSessionHasInput('genres', [$novel->id, $travel->id]);

        // 戻った画面にも、入力値とジャンルのチェックが反映される
        $page = $this->get(route('books.create'));
        $page->assertSee('value="保持される著者"', false);
        $this->assertGenreChecked($page, $novel->id);
        $this->assertGenreChecked($page, $travel->id);
        $this->assertGenreNotChecked($page, $business->id);
    }

    // ------------------------------------------------------------------
    // 更新
    // ------------------------------------------------------------------

    /**
     * B16【更新】編集画面に既存データが初期値として表示され、紐づくジャンルにチェックが入っている
     */
    public function test_b16_edit_form_shows_existing_data_and_checked_genres(): void
    {
        $owner = User::factory()->create();
        $novel = Genre::factory()->create(['name' => '小説']);
        $history = Genre::factory()->create(['name' => '歴史']);
        $cooking = Genre::factory()->create(['name' => '料理']);
        $book = Book::factory()->for($owner)->create([
            'title' => '編集前のタイトル',
            'author' => '編集前 著者',
            'isbn' => '9784000000002',
            'published_date' => '2023-12-25',
            'description' => '編集前の説明文です。',
            'image_url' => 'https://example.com/before.jpg',
        ]);
        $book->genres()->sync([$novel->id, $history->id]);

        $response = $this->actingAs($owner)->get(route('books.edit', $book));

        $response->assertOk()
            ->assertSee('value="編集前のタイトル"', false)
            ->assertSee('value="編集前 著者"', false)
            ->assertSee('value="9784000000002"', false)
            ->assertSee('value="2023-12-25"', false)
            ->assertSee('編集前の説明文です。')
            ->assertSee('value="https://example.com/before.jpg"', false);
        $this->assertGenreChecked($response, $novel->id);
        $this->assertGenreChecked($response, $history->id);
        $this->assertGenreNotChecked($response, $cooking->id);
    }

    /**
     * B17【更新】作成者は書籍を更新できる
     */
    public function test_b17_owner_can_update_book(): void
    {
        $owner = User::factory()->create();
        $novel = Genre::factory()->create(['name' => '小説']);
        $history = Genre::factory()->create(['name' => '歴史']);
        $science = Genre::factory()->create(['name' => '科学']);
        $book = Book::factory()->for($owner)->create(['isbn' => '9784000000002']);
        $book->genres()->sync([$novel->id, $history->id]);

        // 小説を外して、科学を付ける（歴史は残す）
        $response = $this->actingAs($owner)->put(route('books.update', $book), $this->validBookData([
            'title' => '更新後のタイトル',
            'author' => '更新後 著者',
            'isbn' => '9784000000003',
            'published_date' => '2025-01-31',
            'description' => '更新後の説明です。',
            'image_url' => 'https://example.com/after.jpg',
            'genres' => [$history->id, $science->id],
        ]));

        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍情報を更新しました。');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => '更新後のタイトル',
            'author' => '更新後 著者',
            'isbn' => '9784000000003',
            'published_date' => '2025-01-31',
            'description' => '更新後の説明です。',
            'image_url' => 'https://example.com/after.jpg',
        ]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $novel->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $history->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $science->id]);

        $this->get(route('books.show', $book))->assertSee('書籍情報を更新しました。');
    }

    /**
     * B18【更新】自分自身のISBNはそのまま使える
     */
    public function test_b18_update_allows_own_isbn_unchanged(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($owner)->create(['isbn' => '9784000000002']);
        $book->genres()->sync([$genre->id]);

        $this->actingAs($owner)
            ->put(route('books.update', $book), $this->validBookData([
                'title' => 'ISBN変更なしで更新',
                'isbn' => '9784000000002',
                'genres' => [$genre->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'ISBN変更なしで更新',
            'isbn' => '9784000000002',
        ]);
    }

    /**
     * B19【更新】他の書籍のISBNは使えない
     */
    public function test_b19_update_rejects_isbn_of_another_book(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($owner)->create(['isbn' => '9784000000002', 'title' => '更新前のタイトル']);
        $book->genres()->sync([$genre->id]);
        Book::factory()->create(['isbn' => '9784000000009']);

        $this->actingAs($owner)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), $this->validBookData([
                'title' => '更新されてはいけないタイトル',
                'isbn' => '9784000000009',
                'genres' => [$genre->id],
            ]))
            ->assertRedirect(route('books.edit', $book))
            ->assertSessionHasErrors(['isbn' => 'そのISBNは既に使用されています。']);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新前のタイトル',
            'isbn' => '9784000000002',
        ]);
    }

    /**
     * B20【更新】他人は書籍を更新できない
     */
    public function test_b20_other_user_cannot_update_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $novel = Genre::factory()->create(['name' => '小説']);
        $science = Genre::factory()->create(['name' => '科学']);
        $book = Book::factory()->for($owner)->create([
            'title' => '更新前のタイトル',
            'isbn' => '9784000000002',
        ]);
        $book->genres()->sync([$novel->id]);

        $this->actingAs($otherUser)
            ->put(route('books.update', $book), $this->validBookData([
                'title' => '不正に更新されたタイトル',
                'isbn' => '9784000000003',
                'genres' => [$science->id],
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => '更新前のタイトル',
            'isbn' => '9784000000002',
        ]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $novel->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $science->id]);
    }

    // ------------------------------------------------------------------
    // 削除
    // ------------------------------------------------------------------

    /**
     * B21【削除】作成者は書籍を削除でき、関連データも消える
     */
    public function test_b21_owner_can_delete_book_and_related_data_is_removed(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();
        [$book, $review] = $this->createBookWithRelatedData($owner, $otherUser, $genre);

        $this->actingAs($owner)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('success', '書籍を削除しました。');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        // ジャンルとユーザーは消えない
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);

        $this->get(route('books.index'))->assertSee('書籍を削除しました。');
    }

    /**
     * B22【削除】他人は書籍を削除できない
     */
    public function test_b22_other_user_cannot_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();
        [$book, $review] = $this->createBookWithRelatedData($owner, $otherUser, $genre);

        $this->actingAs($otherUser)
            ->delete(route('books.destroy', $book))
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
        $this->assertDatabaseHas('review_likes', ['review_id' => $review->id]);
        $this->assertDatabaseHas('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre->id]);
    }

    // ------------------------------------------------------------------
    // 共通
    // ------------------------------------------------------------------

    /**
     * B23【共通】ゲストは登録・更新・削除できない
     */
    public function test_b23_guest_cannot_store_update_or_delete_book(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['title' => '更新前のタイトル', 'isbn' => '9784000000002']);
        $book->genres()->sync([$genre->id]);

        // 登録
        $this->post(route('books.store'), $this->validBookData(['genres' => [$genre->id]]))
            ->assertRedirect(route('login'));
        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseMissing('books', ['isbn' => '9784000000001']);

        // 更新
        $this->put(route('books.update', $book), $this->validBookData([
            'title' => '不正に更新されたタイトル',
            'isbn' => '9784000000003',
            'genres' => [$genre->id],
        ]))->assertRedirect(route('login'));
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新前のタイトル',
            'isbn' => '9784000000002',
        ]);

        // 削除
        $this->delete(route('books.destroy', $book))
            ->assertRedirect(route('login'));
        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre->id]);
    }

    // ------------------------------------------------------------------
    // ヘルパー
    // ------------------------------------------------------------------

    /**
     * 登録・更新リクエストの正しい入力値を作る。genres は呼び出し側で指定する。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validBookData(array $overrides = []): array
    {
        return [
            'title' => '登録テストの本',
            'author' => '登録 太郎',
            'isbn' => '9784000000001',
            'published_date' => '2024-05-01',
            'description' => '登録テストの説明です。',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => [],
            ...$overrides,
        ];
    }

    /**
     * レビュー（いいねつき）・お気に入り・ジャンルの紐付けを持つ書籍を作る。
     *
     * @return array{0: Book, 1: Review}
     */
    private function createBookWithRelatedData(User $owner, User $otherUser, Genre $genre): array
    {
        $book = Book::factory()->for($owner)->create();
        $book->genres()->sync([$genre->id]);

        $review = Review::factory()->for($book)->for($otherUser)->create();
        $review->likedByUsers()->attach($owner->id);
        $otherUser->favoriteBooks()->attach($book->id);

        return [$book, $review];
    }

    /**
     * 指定したジャンルのチェックボックスに checked が付いていることを確認する。
     */
    private function assertGenreChecked(TestResponse $response, int $genreId): void
    {
        $this->assertMatchesRegularExpression(
            $this->genreCheckboxPattern($genreId, true),
            $response->getContent(),
            "ジャンルID {$genreId} のチェックボックスが checked になっていません。"
        );
    }

    /**
     * 指定したジャンルのチェックボックスに checked が付いていないことを確認する。
     */
    private function assertGenreNotChecked(TestResponse $response, int $genreId): void
    {
        $this->assertMatchesRegularExpression(
            $this->genreCheckboxPattern($genreId, false),
            $response->getContent(),
            "ジャンルID {$genreId} のチェックボックスが存在しない、または checked になっています。"
        );
    }

    private function genreCheckboxPattern(int $genreId, bool $checked): string
    {
        $checkboxStart = '<input type="checkbox" name="genres\[\]" value="'.$genreId.'"';

        // checked あり: タグの中に checked が含まれる／なし: タグの中に checked が含まれない
        return $checked
            ? '/'.$checkboxStart.'[^>]*\schecked\s*>/'
            : '/'.$checkboxStart.'(?:(?!\schecked)[^>])*>/';
    }
}
