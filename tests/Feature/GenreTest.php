<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ジャンルのテスト（ケースID G1〜G15）。
 */
class GenreTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 一覧
    // ------------------------------------------------------------------

    /**
     * G1【一覧】各ジャンルと書籍数が表示される
     */
    public function test_g1_index_shows_genres_with_books_count(): void
    {
        $novel = Genre::factory()->create(['name' => '小説']);
        $history = Genre::factory()->create(['name' => '歴史']);
        Genre::factory()->create(['name' => '料理']); // 書籍が紐付いていないジャンル
        $novel->books()->attach(Book::factory()->count(3)->create()->pluck('id'));
        $history->books()->attach(Book::factory()->create()->id);

        $this->actingAs(User::factory()->create())
            ->get(route('genres.index'))
            ->assertOk()
            ->assertSee('小説')
            ->assertSee('3冊')
            ->assertSee('歴史')
            ->assertSee('1冊')
            ->assertSee('料理')
            ->assertSee('0冊');
    }

    /**
     * G2【一覧】ジャンルが登録順で表示される
     */
    public function test_g2_index_shows_genres_in_registration_order(): void
    {
        // 名前順（五十音順）ではなく、登録順に並ぶことを確かめるため、あえてバラバラの名前で作る
        Genre::factory()->create(['name' => '料理']);
        Genre::factory()->create(['name' => '小説']);
        Genre::factory()->create(['name' => '歴史']);

        $this->actingAs(User::factory()->create())
            ->get(route('genres.index'))
            ->assertOk()
            ->assertSeeInOrder(['料理', '小説', '歴史']);
    }

    /**
     * G3【一覧】ジャンルが0件のときはメッセージが表示される
     */
    public function test_g3_index_shows_message_when_no_genres(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('genres.index'))
            ->assertOk()
            ->assertSee('ジャンルが登録されていません。');
    }

    // ------------------------------------------------------------------
    // 詳細
    // ------------------------------------------------------------------

    /**
     * G4【詳細】ジャンル名と、そのジャンルの書籍だけが表示される
     */
    public function test_g4_show_displays_genre_name_and_only_its_books(): void
    {
        $novel = Genre::factory()->create(['name' => '小説']);
        $cooking = Genre::factory()->create(['name' => '料理']);
        $novel->books()->attach(Book::factory()->create(['title' => '小説ジャンルの書籍A'])->id);
        $novel->books()->attach(Book::factory()->create(['title' => '小説ジャンルの書籍B'])->id);
        $cooking->books()->attach(Book::factory()->create(['title' => '料理ジャンルだけの書籍'])->id);

        $this->actingAs(User::factory()->create())
            ->get(route('genres.show', $novel))
            ->assertOk()
            ->assertSee('小説')
            ->assertSee('小説ジャンルの書籍A')
            ->assertSee('小説ジャンルの書籍B')
            ->assertDontSee('料理ジャンルだけの書籍');
    }

    /**
     * G5【詳細】書籍が10件ずつ・新しい順で表示される
     */
    public function test_g5_show_displays_10_books_per_page_newest_first(): void
    {
        $genre = Genre::factory()->create();
        $user = User::factory()->create();

        // created_at を1日ずつずらして11件作る（番号が大きいほど新しい）
        foreach (range(1, 11) as $number) {
            $book = Book::factory()->create([
                'title' => sprintf('ジャンル詳細テスト書籍%02d', $number),
                'created_at' => now()->subDays(12 - $number),
            ]);
            $genre->books()->attach($book->id);
        }

        $firstPage = $this->actingAs($user)->get(route('genres.show', $genre));

        $firstPage->assertOk();
        $titles = $firstPage->viewData('books')->pluck('title')->all();
        $this->assertCount(10, $titles);
        $this->assertSame('ジャンル詳細テスト書籍11', $titles[0]);
        $this->assertSame('ジャンル詳細テスト書籍02', $titles[9]);
        $firstPage->assertDontSee('ジャンル詳細テスト書籍01');

        $secondPage = $this->actingAs($user)->get(route('genres.show', ['genre' => $genre, 'page' => 2]));

        $secondPage->assertOk();
        $this->assertSame(['ジャンル詳細テスト書籍01'], $secondPage->viewData('books')->pluck('title')->all());
    }

    /**
     * G6【詳細】書籍が0件のときはメッセージが表示される
     */
    public function test_g6_show_displays_message_when_no_books(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('このジャンルの書籍はまだ登録されていません。');
    }

    // ------------------------------------------------------------------
    // 登録
    // ------------------------------------------------------------------

    /**
     * G7【登録】ジャンルを登録できる
     */
    public function test_g7_user_can_store_genre(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('genres.store'), ['name' => '自己啓発']);

        $response->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを作成しました。');
        $this->assertDatabaseHas('genres', ['name' => '自己啓発']);
        $this->assertDatabaseCount('genres', 1);

        $this->get(route('genres.index'))
            ->assertSee('ジャンルを作成しました。')
            ->assertSee('自己啓発');
    }

    /**
     * G8【登録】ジャンル名が空・256文字以上・重複だと登録できない
     */
    #[DataProvider('invalidGenreNameProvider')]
    public function test_g8_store_rejects_invalid_name(string $name, string $message): void
    {
        Genre::factory()->create(['name' => '既存ジャンル']);

        $response = $this->actingAs(User::factory()->create())
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => $name]);

        $response->assertRedirect(route('genres.create'))
            ->assertSessionHasErrors(['name' => $message]);
        if ($name !== '') {
            $response->assertSessionHasInput('name', $name);
        }

        // 登録済みの1件（既存ジャンル）から増えていない
        $this->assertDatabaseCount('genres', 1);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidGenreNameProvider(): array
    {
        return [
            '空' => ['', 'ジャンル名は必須です。'],
            '256文字' => [str_repeat('あ', 256), 'ジャンル名は255文字以内で入力してください。'],
            '重複' => ['既存ジャンル', 'そのジャンル名は既に使用されています。'],
        ];
    }

    // ------------------------------------------------------------------
    // 更新
    // ------------------------------------------------------------------

    /**
     * G9【更新】編集画面に現在のジャンル名が初期値として表示される
     */
    public function test_g9_edit_form_shows_current_genre_name(): void
    {
        $genre = Genre::factory()->create(['name' => '編集前のジャンル']);

        $this->actingAs(User::factory()->create())
            ->get(route('genres.edit', $genre))
            ->assertOk()
            ->assertSee('編集前のジャンル')
            ->assertSee('value="編集前のジャンル"', false);
    }

    /**
     * G10【更新】ジャンル名を更新できる（自分と同じ名前のままでも可）
     */
    public function test_g10_user_can_update_genre_including_unchanged_name(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '更新前のジャンル']);

        $this->actingAs($user)
            ->put(route('genres.update', $genre), ['name' => '更新後のジャンル'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '更新後のジャンル']);

        // 自分と同じ名前のままでも、重複エラーにならず更新できる
        $this->actingAs($user)
            ->put(route('genres.update', $genre), ['name' => '更新後のジャンル'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'ジャンルを更新しました。');
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '更新後のジャンル']);
        $this->assertDatabaseCount('genres', 1);

        $this->get(route('genres.index'))->assertSee('ジャンルを更新しました。');
    }

    /**
     * G11【更新】他のジャンルと同じ名前には更新できない
     */
    public function test_g11_update_rejects_name_of_another_genre(): void
    {
        $genre = Genre::factory()->create(['name' => '更新前のジャンル']);
        Genre::factory()->create(['name' => '別のジャンル']);

        $this->actingAs(User::factory()->create())
            ->from(route('genres.edit', $genre))
            ->put(route('genres.update', $genre), ['name' => '別のジャンル'])
            ->assertRedirect(route('genres.edit', $genre))
            ->assertSessionHasErrors(['name' => 'そのジャンル名は既に使用されています。'])
            ->assertSessionHasInput('name', '別のジャンル');

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '更新前のジャンル']);
        $this->assertDatabaseCount('genres', 2);
    }

    // ------------------------------------------------------------------
    // 削除
    // ------------------------------------------------------------------

    /**
     * G12【削除】書籍が紐づかないジャンルは削除できる
     */
    public function test_g12_genre_without_books_can_be_deleted(): void
    {
        $genre = Genre::factory()->create(['name' => '削除するジャンル']);

        $this->actingAs(User::factory()->create())
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);

        $this->get(route('genres.index'))->assertSee('ジャンルを削除しました。');
    }

    /**
     * G13【削除】書籍が紐づくジャンルは削除できない
     */
    public function test_g13_genre_with_books_cannot_be_deleted(): void
    {
        $genre = Genre::factory()->create(['name' => '削除できないジャンル']);
        $book = Book::factory()->create();
        $genre->books()->attach($book->id);

        $this->actingAs(User::factory()->create())
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', 'このジャンルには書籍が紐付いているため削除できません。')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '削除できないジャンル']);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre->id]);

        $this->get(route('genres.index'))->assertSee('このジャンルには書籍が紐付いているため削除できません。');
    }

    // ------------------------------------------------------------------
    // 共通
    // ------------------------------------------------------------------

    /**
     * G14【共通】どのログインユーザーでもジャンルを編集・削除できる（Policyがない）
     */
    public function test_g14_any_logged_in_user_can_update_and_delete_genre(): void
    {
        // ジャンルに登録者はいないので、本・ジャンルとは無関係の別ユーザーで操作する
        $anotherUser = User::factory()->create();
        $updatedGenre = Genre::factory()->create(['name' => '更新されるジャンル']);
        $deletedGenre = Genre::factory()->create(['name' => '削除されるジャンル']);

        $this->actingAs($anotherUser)
            ->put(route('genres.update', $updatedGenre), ['name' => '別ユーザーが更新したジャンル'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');
        $this->assertDatabaseHas('genres', ['id' => $updatedGenre->id, 'name' => '別ユーザーが更新したジャンル']);

        $this->actingAs($anotherUser)
            ->delete(route('genres.destroy', $deletedGenre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');
        $this->assertDatabaseMissing('genres', ['id' => $deletedGenre->id]);
    }

    /**
     * G15【共通】ゲストはジャンル機能を使えない
     */
    public function test_g15_guest_cannot_use_genre_features(): void
    {
        $genre = Genre::factory()->create(['name' => 'ゲスト操作前のジャンル']);

        $this->get(route('genres.index'))->assertRedirect(route('login'));
        $this->get(route('genres.create'))->assertRedirect(route('login'));
        $this->post(route('genres.store'), ['name' => 'ゲストが登録したジャンル'])->assertRedirect(route('login'));
        $this->get(route('genres.show', $genre))->assertRedirect(route('login'));
        $this->get(route('genres.edit', $genre))->assertRedirect(route('login'));
        $this->put(route('genres.update', $genre), ['name' => 'ゲストが更新したジャンル'])->assertRedirect(route('login'));
        $this->delete(route('genres.destroy', $genre))->assertRedirect(route('login'));

        $this->assertDatabaseCount('genres', 1);
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => 'ゲスト操作前のジャンル']);
    }
}
