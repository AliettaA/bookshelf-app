<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * お気に入りのテスト（ケースID F1〜F10）。
 */
class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 追加・解除
    // ------------------------------------------------------------------

    /**
     * F1【追加】書籍詳細からお気に入りに追加できる
     */
    public function test_f1_user_can_add_favorite_from_book_show(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'お気に入りに追加しました。');

        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
        $this->assertDatabaseCount('favorites', 1);

        $this->get(route('books.show', $book))->assertSee('お気に入りに追加しました。');
    }

    /**
     * F2【解除】追加済みの書籍で押すと解除される
     */
    public function test_f2_pressing_again_removes_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);

        $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'お気に入りを解除しました。');

        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
    }

    /**
     * F3【切り替え】追加→解除→再追加が正しく切り替わる
     */
    public function test_f3_toggle_switches_between_add_remove_and_add_again(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $favorite = ['user_id' => $user->id, 'book_id' => $book->id];

        // 1回目：追加
        $this->actingAs($user)->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertSessionHas('success', 'お気に入りに追加しました。');
        $this->assertDatabaseHas('favorites', $favorite);

        // 2回目：解除
        $this->actingAs($user)->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertSessionHas('success', 'お気に入りを解除しました。');
        $this->assertDatabaseMissing('favorites', $favorite);

        // 3回目：再追加（重複せず1件になる）
        $this->actingAs($user)->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertSessionHas('success', 'お気に入りに追加しました。');
        $this->assertDatabaseHas('favorites', $favorite);
        $this->assertDatabaseCount('favorites', 1);
    }

    /**
     * F4【解除】お気に入り一覧から解除すると、一覧に戻り書籍が消える
     */
    public function test_f4_removing_from_favorites_list_returns_to_list_and_book_disappears(): void
    {
        $user = User::factory()->create();
        $removedBook = Book::factory()->create(['title' => '一覧から解除する書籍']);
        $remainingBook = Book::factory()->create(['title' => '一覧に残る書籍']);
        $user->favoriteBooks()->attach([$removedBook->id, $remainingBook->id]);

        $this->actingAs($user)
            ->from('/favorites')
            ->post(route('favorites.toggle', $removedBook))
            ->assertRedirect('/favorites')
            ->assertSessionHas('success', 'お気に入りを解除しました。');

        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'book_id' => $removedBook->id]);
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $remainingBook->id]);

        $this->get(route('favorites.index'))
            ->assertOk()
            ->assertDontSee('一覧から解除する書籍')
            ->assertSee('一覧に残る書籍');
    }

    /**
     * F5【権限】ゲストはお気に入りできない
     */
    public function test_f5_guest_cannot_toggle_favorite(): void
    {
        $book = Book::factory()->create();

        $this->post(route('favorites.toggle', $book))
            ->assertRedirect(route('login'));
        $this->get(route('favorites.index'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('favorites', 0);
    }

    // ------------------------------------------------------------------
    // 一覧
    // ------------------------------------------------------------------

    /**
     * F6【一覧】お気に入り一覧に自分のお気に入りが10件ずつ表示される
     */
    public function test_f6_favorites_index_shows_10_per_page(): void
    {
        $user = User::factory()->create();
        $user->favoriteBooks()->attach(Book::factory()->count(11)->create()->pluck('id'));

        $firstPage = $this->actingAs($user)->get(route('favorites.index'));

        $firstPage->assertOk();
        $this->assertCount(10, $firstPage->viewData('books'));
        $this->assertSame(11, $firstPage->viewData('books')->total());

        $secondPage = $this->actingAs($user)->get(route('favorites.index', ['page' => 2]));

        $secondPage->assertOk();
        $this->assertCount(1, $secondPage->viewData('books'));
    }

    /**
     * F7【一覧】お気に入り一覧は追加日時の新しい順に表示される
     */
    public function test_f7_favorites_index_is_ordered_by_added_time_newest_first(): void
    {
        $user = User::factory()->create();
        // 書籍の登録順（id順）とお気に入りに追加した順が逆になるようにする
        $firstBook = Book::factory()->create(['title' => '最初に登録した書籍']);
        $secondBook = Book::factory()->create(['title' => '二番目に登録した書籍']);
        $thirdBook = Book::factory()->create(['title' => '三番目に登録した書籍']);

        // お気に入りの追加日時（favorites.created_at）をずらして追加する
        $this->travelTo(now()->subDays(3));
        $user->favoriteBooks()->attach($secondBook->id);
        $this->travelTo(now()->addDay());
        $user->favoriteBooks()->attach($thirdBook->id);
        $this->travelTo(now()->addDay());
        $user->favoriteBooks()->attach($firstBook->id);
        $this->travelBack();

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSeeInOrder(['最初に登録した書籍', '三番目に登録した書籍', '二番目に登録した書籍']);
    }

    /**
     * F8【一覧】お気に入り一覧に他人のお気に入りは表示されない
     */
    public function test_f8_favorites_index_does_not_show_other_users_favorites(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownBook = Book::factory()->create(['title' => '自分のお気に入り書籍']);
        $othersBook = Book::factory()->create(['title' => '他人のお気に入り書籍']);
        $user->favoriteBooks()->attach($ownBook->id);
        $otherUser->favoriteBooks()->attach($othersBook->id);

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('自分のお気に入り書籍')
            ->assertDontSee('他人のお気に入り書籍');
    }

    /**
     * F9【表示】書籍詳細のお気に入りボタンが状態で切り替わる
     */
    public function test_f9_book_show_favorite_button_changes_by_state(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $toggleForm = '<form action="'.route('favorites.toggle', $book).'"';

        // ゲスト：ログイン画面へのリンクが表示され、お気に入りのフォームは出ない
        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('お気に入りに追加するにはログインしてください')
            ->assertSee('href="'.route('login').'" class="text-gray-400 hover:text-red-500"', false)
            ->assertDontSee($toggleForm, false);

        // 未追加：「お気に入りに追加」が表示される
        $this->actingAs($user)->get(route('books.show', $book))
            ->assertOk()
            ->assertSee($toggleForm, false)
            ->assertSee('お気に入りに追加')
            ->assertDontSee('お気に入りから削除');

        // 追加済み：「お気に入りから削除」が表示される
        $user->favoriteBooks()->attach($book->id);

        // 前のリクエストで読み込み済みのリレーションが残らないよう、DBから取得し直したユーザーを使う
        $this->actingAs($user->fresh())->get(route('books.show', $book))
            ->assertOk()
            ->assertSee($toggleForm, false)
            ->assertSee('お気に入りから削除')
            ->assertDontSee('お気に入りに追加');
    }

    /**
     * F10【一覧】お気に入りがない場合はメッセージが表示される
     */
    public function test_f10_favorites_index_shows_message_when_no_favorites(): void
    {
        // 他人のお気に入りがあっても、自分に0件ならメッセージが出る
        $otherUser = User::factory()->create();
        $otherUser->favoriteBooks()->attach(Book::factory()->create()->id);

        $this->actingAs(User::factory()->create())
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('お気に入りに登録された書籍はありません。');
    }
}
