<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * レビューのテスト（ケースID R1〜R18）。
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 表示
    // ------------------------------------------------------------------

    /**
     * R1【表示】投稿フォームはログイン時だけ表示され、ゲストにはログインの案内が表示される
     */
    public function test_r1_review_form_is_shown_only_to_logged_in_user(): void
    {
        $book = Book::factory()->create();
        $storeForm = '<form action="'.route('reviews.store', $book).'"';

        // ゲスト：ログインの案内が表示され、フォームは出ない
        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('レビューを投稿するには')
            ->assertDontSee($storeForm, false);

        // ログイン時：フォームが表示され、ログインの案内は出ない
        $this->actingAs(User::factory()->create())
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('レビューを投稿')
            ->assertSee($storeForm, false)
            ->assertDontSee('レビューを投稿するには');
    }

    /**
     * R2【表示】レビュー一覧に投稿者名・評価・コメント・投稿日が表示される
     */
    public function test_r2_review_list_shows_user_name_rating_comment_and_date(): void
    {
        $book = Book::factory()->create();
        $reviewer = User::factory()->create(['name' => 'レビュー太郎']);
        Review::factory()->for($book)->for($reviewer)->create([
            'rating' => 4,
            'comment' => '表示確認用のコメントです。',
            'created_at' => '2024-03-15 10:00:00',
        ]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('レビュー太郎')
            ->assertSee('★★★★☆')
            ->assertSee('表示確認用のコメントです。')
            ->assertSee('2024/03/15');
    }

    /**
     * R3【表示】レビューが新しい順に表示される
     */
    public function test_r3_reviews_are_shown_newest_first(): void
    {
        $book = Book::factory()->create();
        Review::factory()->for($book)->create([
            'comment' => '一番古いレビュー',
            'created_at' => now()->subDays(3),
        ]);
        Review::factory()->for($book)->create([
            'comment' => '一番新しいレビュー',
            'created_at' => now()->subDay(),
        ]);
        Review::factory()->for($book)->create([
            'comment' => '真ん中のレビュー',
            'created_at' => now()->subDays(2),
        ]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSeeInOrder(['一番新しいレビュー', '真ん中のレビュー', '一番古いレビュー']);
    }

    /**
     * R4【表示】レビューがないときはメッセージが表示される
     */
    public function test_r4_shows_message_when_no_reviews(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('まだレビューはありません。');
    }

    /**
     * R5【表示】編集・削除ボタンは投稿者にだけ表示される
     */
    public function test_r5_edit_and_delete_buttons_are_shown_only_to_review_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($book)->for($owner)->create();

        $editLink = 'href="'.route('reviews.edit', $review).'"';
        $deleteForm = '<form action="'.route('reviews.destroy', $review).'"';

        // ゲストには表示されない
        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertDontSee($editLink, false)
            ->assertDontSee($deleteForm, false);

        // 他のユーザーには表示されない
        $this->actingAs($otherUser)->get(route('books.show', $book))
            ->assertOk()
            ->assertDontSee($editLink, false)
            ->assertDontSee($deleteForm, false);

        // 投稿者には表示される
        $this->actingAs($owner)->get(route('books.show', $book))
            ->assertOk()
            ->assertSee($editLink, false)
            ->assertSee($deleteForm, false);
    }

    // ------------------------------------------------------------------
    // 投稿
    // ------------------------------------------------------------------

    /**
     * R6【投稿】レビューを投稿できる
     */
    public function test_r6_user_can_store_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 5,
            'comment' => '投稿テストのコメントです。',
        ]);

        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'レビューを投稿しました。');
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '投稿テストのコメントです。',
        ]);

        $this->get(route('books.show', $book))->assertSee('レビューを投稿しました。');
    }

    /**
     * R7【投稿】同じユーザーが同じ書籍に複数のレビューを投稿できる
     */
    public function test_r7_user_can_store_multiple_reviews_for_same_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->post(route('reviews.store', $book), ['rating' => 4, 'comment' => '1件目のレビュー'])
            ->assertRedirect(route('books.show', $book));
        $this->actingAs($user)
            ->post(route('reviews.store', $book), ['rating' => 3, 'comment' => '2件目のレビュー'])
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'book_id' => $book->id, 'comment' => '1件目のレビュー']);
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'book_id' => $book->id, 'comment' => '2件目のレビュー']);
    }

    /**
     * R8【投稿】評価が未選択・範囲外だと投稿できない
     */
    #[DataProvider('invalidRatingProvider')]
    public function test_r8_store_rejects_invalid_rating(string $rating, string $message): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => $rating,
                'comment' => '評価が不正なときのコメント',
            ]);

        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHasErrors(['rating' => $message])
            ->assertSessionHasInput('comment', '評価が不正なときのコメント');
        if ($rating !== '') {
            $response->assertSessionHasInput('rating', $rating);
        }

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidRatingProvider(): array
    {
        return [
            '未選択' => ['', '評価は必須です。'],
            '0' => ['0', '評価は1〜5の範囲で選択してください。'],
            '6' => ['6', '評価は1〜5の範囲で選択してください。'],
            '文字列' => ['abc', '評価は1〜5の範囲で選択してください。'],
        ];
    }

    /**
     * R9【投稿】コメントが空・1001文字以上だと投稿できない
     */
    #[DataProvider('invalidCommentProvider')]
    public function test_r9_store_rejects_invalid_comment(string $comment, string $message): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), ['rating' => 3, 'comment' => $comment])
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHasErrors(['comment' => $message]);

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidCommentProvider(): array
    {
        return [
            '空' => ['', 'コメントは必須です。'],
            '1001文字' => [str_repeat('あ', 1001), 'コメントは1000文字以内で入力してください。'],
        ];
    }

    /**
     * R10【投稿】ゲストは投稿できない
     */
    public function test_r10_guest_cannot_store_review(): void
    {
        $book = Book::factory()->create();

        $this->post(route('reviews.store', $book), ['rating' => 5, 'comment' => 'ゲストの投稿'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * R11【投稿】存在しない書籍にはレビューできない
     */
    public function test_r11_cannot_store_review_for_nonexistent_book(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/books/9999/reviews', ['rating' => 5, 'comment' => '存在しない書籍へのレビュー'])
            ->assertNotFound();

        $this->assertDatabaseCount('reviews', 0);
    }

    // ------------------------------------------------------------------
    // 更新
    // ------------------------------------------------------------------

    /**
     * R12【更新】編集画面に書籍名・既存の評価・コメントが初期値として表示される
     */
    public function test_r12_edit_form_shows_book_title_and_existing_values(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->create(['title' => 'レビュー編集テストの本']);
        $review = Review::factory()->for($book)->for($owner)->create([
            'rating' => 4,
            'comment' => '編集前のコメントです。',
        ]);

        $this->actingAs($owner)
            ->get(route('reviews.edit', $review))
            ->assertOk()
            ->assertSee('レビュー編集テストの本')
            ->assertSee('編集前のコメントです。')
            ->assertSee('name="rating" value="4" class="sr-only peer" checked', false)
            ->assertDontSee('name="rating" value="3" class="sr-only peer" checked', false)
            ->assertDontSee('name="rating" value="5" class="sr-only peer" checked', false);
    }

    /**
     * R13【更新】投稿者はレビューを更新できる
     */
    public function test_r13_owner_can_update_review(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($book)->for($owner)->create([
            'rating' => 2,
            'comment' => '更新前のコメント',
        ]);

        $this->actingAs($owner)
            ->put(route('reviews.update', $review), ['rating' => 5, 'comment' => '更新後のコメント'])
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'レビューを更新しました。');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '更新後のコメント',
        ]);
        $this->assertDatabaseCount('reviews', 1);

        $this->get(route('books.show', $book))->assertSee('レビューを更新しました。');
    }

    /**
     * R14【更新】更新時のバリデーションエラーは、入力を保持して編集画面に戻る
     *
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidUpdateInputProvider')]
    public function test_r14_update_validation_error_returns_to_edit_form_with_old_input(array $input, string $field, string $message): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create([
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);

        $response = $this->actingAs($owner)
            ->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), $input);

        $response->assertRedirect(route('reviews.edit', $review))
            ->assertSessionHasErrors([$field => $message])
            ->assertSessionHasInput('rating', $input['rating']);
        if ($input['comment'] !== '') {
            $response->assertSessionHasInput('comment', $input['comment']);
        }

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function invalidUpdateInputProvider(): array
    {
        return [
            '評価が範囲外' => [
                ['rating' => '6', 'comment' => '更新しようとしたコメント'],
                'rating',
                '評価は1〜5の範囲で選択してください。',
            ],
            'コメントが空' => [
                ['rating' => '5', 'comment' => ''],
                'comment',
                'コメントは必須です。',
            ],
        ];
    }

    /**
     * R15【更新】他人はレビューを更新できない
     */
    public function test_r15_other_user_cannot_update_review(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->for($owner)->create([
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);

        $this->actingAs($otherUser)
            ->put(route('reviews.update', $review), ['rating' => 1, 'comment' => '他人による更新'])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $owner->id,
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);
    }

    // ------------------------------------------------------------------
    // 削除
    // ------------------------------------------------------------------

    /**
     * R16【削除】投稿者はレビューを削除でき、いいねも消える
     */
    public function test_r16_owner_can_delete_review_and_likes_are_removed(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($book)->for($owner)->create();
        $review->likedByUsers()->attach([$owner->id, $liker->id]);
        $this->assertDatabaseCount('review_likes', 2);

        $this->actingAs($owner)
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'レビューを削除しました。');

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
        $this->assertDatabaseCount('review_likes', 0);

        $this->get(route('books.show', $book))->assertSee('レビューを削除しました。');
    }

    /**
     * R17【削除】他人はレビューを削除できない
     */
    public function test_r17_other_user_cannot_delete_review(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->for($owner)->create();
        $review->likedByUsers()->attach($otherUser->id);

        $this->actingAs($otherUser)
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('review_likes', ['review_id' => $review->id, 'user_id' => $otherUser->id]);
    }

    // ------------------------------------------------------------------
    // 共通
    // ------------------------------------------------------------------

    /**
     * R18【共通】ゲストは更新・削除できない
     */
    public function test_r18_guest_cannot_update_or_delete_review(): void
    {
        $review = Review::factory()->create([
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);

        $this->put(route('reviews.update', $review), ['rating' => 1, 'comment' => 'ゲストによる更新'])
            ->assertRedirect(route('login'));
        $this->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);
    }
}
