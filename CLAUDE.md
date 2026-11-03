# BookShelf 書籍レビューアプリ（模擬案件）

模擬案件。Blade テンプレートは完成品として提供済みで、**バックエンド（DB・認証・認可・CRUD・公開API・テスト）を実装する**のが仕事。
仕様の多くはクライアントPM（コーチ）へのヒアリングで確定している。このファイルに書かれた仕様を正とし、書かれていない判断が必要になったら**実装を止めて人間に確認する**こと。

---

## 1. 絶対に守るルール

- `resources/views/` 配下の Blade は**変更しない**。Blade が期待するルート名・変数名・リレーション名に、こちらが合わせる
- 要件にない機能を勝手に追加しない（管理者機能、論理削除、パスワードリセット等）
- `laravel-lang/*` 系パッケージは導入しない（日本語化は `lang/ja/` の手動配置で対応済み）
- バリデーションは必ず **FormRequest** に分離し、全 FormRequest に `messages()` で日本語メッセージを定義する（会員登録・ログインは Fortify 標準＋`lang/ja`）
- 認可は必ず **Policy** で実装し、コントローラーで `$this->authorize()` を使う
- DB操作は **Eloquent** を使う（クエリビルダ・生SQLは原則使わない）。N+1 を避けるため `with()` / `load()` / `withCount()` / `withAvg()` を使う
- コントローラーはリクエスト受付とレスポンス返却に専念させる
- 設定値（DB接続・APIキー等）は `.env` で管理し、ハードコーディングしない
- 命名は PSR-12 / Laravel 規約（変数・メソッド camelCase、クラス PascalCase、テーブル snake_case 複数形、カラム snake_case 単数形）。ローマ字や無意味な名前（a, x 等）は使わない
- コミット前に `sail bin pint` を実行する。採点基準は `sail bin pint --test` が「No fixable issues were found」
- 応用機能（★）の要件（型宣言の徹底、PHPDoc、Sanctum、読書計画など）は、基本機能の完成後に着手する。今は実装しない

## 2. 環境

- PHP 8.5 / Laravel 10.x / MySQL 8.4 / Laravel Sail / phpMyAdmin（http://localhost:8080）
- フロント：Vite, Tailwind CSS ^3.4.0, @tailwindcss/forms, Alpine.js（`sail npm run dev` を起動しておく）
- コマンドはすべて `sail` 経由（例：`sail artisan migrate:fresh --seed`、`sail artisan test`）
- `config/app.php`：`locale` = `ja`、`timezone` = `Asia/Tokyo`
- `lang/ja/auth.php` の `failed` = `ログイン情報が登録されていません。`
- `lang/ja/validation.php`：`unique` = `その:attributeは既に使用されています。`、`url` = `:attributeは有効なURL形式で入力してください。`、`attributes` の `name` = `お名前`、`password_confirmation` = `パスワード確認`

## 3. Git 運用

- 機能ごとにブランチを作り、main にマージする
- ブランチ例：`feature/database` → `feature/auth` → `feature/books` → `feature/genres` → `feature/reviews` → `feature/favorites-likes` → `feature/ranking` → `feature/api` → `feature/tests`
- コミットメッセージは「何をしたか」が分かる形（例：`feat: 書籍一覧表示を実装`）

---

## 4. データベース

### テーブル

| テーブル | カラム | 補足 |
|---|---|---|
| users | id, name(255), email(255, UNIQUE), email_verified_at(null), password, two_factor_*（Fortifyが追加）, remember_token, timestamps | |
| books | id, user_id(FK users, cascadeOnDelete), title(255), author(255), isbn(varchar 13, UNIQUE), published_date(date, NOT NULL), description(text, null), image_url(255, null), timestamps | |
| genres | id, name(255, UNIQUE), timestamps | |
| book_genre | id, book_id(FK books, cascadeOnDelete), genre_id(FK genres, restrictOnDelete), timestamps | UNIQUE(book_id, genre_id) |
| reviews | id, user_id(FK users, cascadeOnDelete), book_id(FK books, cascadeOnDelete), rating(tinyint unsigned), comment(text, NOT NULL), timestamps | **複合UNIQUEなし**（同じ書籍に同じユーザーが複数レビュー可） |
| favorites | id, user_id(FK users, cascadeOnDelete), book_id(FK books, cascadeOnDelete), timestamps | UNIQUE(user_id, book_id) |
| review_likes | id, user_id(FK users, cascadeOnDelete), review_id(FK reviews, cascadeOnDelete), timestamps | UNIQUE(user_id, review_id) |

- `books.published_date` は**日付型キャストをしない**（Blade が `Y-m-d` の文字列のまま `{{ }}` と `type="date"` の value に出力しているため。キャストすると時刻付きになり編集フォームが空欄になる）
- 書籍削除時は、レビュー（とそのいいね）・お気に入り・ジャンル紐付けも削除される（外部キーの cascadeOnDelete で実現）
- 中間テーブルは `withTimestamps()` を付ける

### リレーション（名前は Blade で使われているので固定）

| モデル | メソッド |
|---|---|
| User | `books()` hasMany、`reviews()` hasMany、`favoriteBooks()` belongsToMany(Book, 'favorites')、`likedReviews()` belongsToMany(Review, 'review_likes') |
| Book | `user()` belongsTo、`genres()` belongsToMany(Genre, 'book_genre')、`reviews()` hasMany |
| Genre | `books()` belongsToMany(Book, 'book_genre') |
| Review | `user()` belongsTo、`book()` belongsTo、`likedByUsers()` belongsToMany(User, 'review_likes') |

### Seeder（シーディング要件どおり。DatabaseSeeder の実行順もこの順）

1. **UserSeeder**：山田太郎 yamada@example.com / 鈴木花子 suzuki@example.com / 田中一郎 tanaka@example.com / 佐藤美咲 sato@example.com / 高橋健太 takahashi@example.com（パスワードはすべて `password`、`Hash::make()`、`firstOrCreate` で email 重複防止）
2. **GenreSeeder**：小説、ビジネス、技術書、自己啓発、エッセイ、歴史、科学、芸術、料理、旅行（`firstOrCreate`）
3. **BookSeeder**：案件シートの11冊。登録者は `User::first()`。description を設定、image_url は `https://placehold.co/200x300/e2e8f0/475569?text={番号}`（1〜11）。`firstOrCreate`（ISBN）＋ `genres()->sync()`
4. **ReviewSeeder**：32件。5人が11冊に、rating 3〜5、各書籍2〜4件、具体的なコメント。`create`
5. **FavoriteSeeder**：各ユーザー3〜5冊。`syncWithoutDetaching`
6. **ReviewLikeSeeder**：各レビューに0〜3人（自分のレビューを除く）。`syncWithoutDetaching`

（書籍データ・ISBN・出版日・ジャンルの詳細は案件シート⑨シーディング要件を参照）

---

## 5. 認証（Laravel Fortify）

- Fortify を導入し、`FortifyServiceProvider` で `loginView` → `auth.login`、`registerView` → `auth.register` を登録
- `config/fortify.php` の features は **registration のみ**（Blade にパスワードリセット等の画面がないため）
- ログイン・会員登録後の遷移先は `/`（`RouteServiceProvider::HOME` を `/` に）
- ログアウト後は `/login`
- ログイン済みで `/login`・`/register` にアクセス → `/`
- 未ログインで認証必須ページ → `/login`
- 会員登録のルール（CreateNewUser）：name `required|string|max:255`、email `required|string|email|max:255|unique:users`、password `required|string|min:8|confirmed`（Fortify 標準の Password ルール）
- ログインは Fortify 標準（email・password の必須チェックのみ）。失敗時のメッセージは `ログイン情報が登録されていません。`

---

## 6. ルーティング（web.php）

| メソッド | URI | ルート名 | ミドルウェア | 認可 |
|---|---|---|---|---|
| GET | `/` | （books.index と同じ画面） | — | — |
| GET | `/books` | books.index | — | — |
| GET | `/books/create` | books.create | auth | — |
| POST | `/books` | books.store | auth | — |
| GET | `/books/{book}` | books.show | — | — |
| GET | `/books/{book}/edit` | books.edit | auth | BookPolicy@update |
| PUT | `/books/{book}` | books.update | auth | BookPolicy@update |
| DELETE | `/books/{book}` | books.destroy | auth | BookPolicy@delete |
| POST | `/books/{book}/reviews` | reviews.store | auth | — |
| GET | `/reviews/{review}/edit` | reviews.edit | auth | ReviewPolicy@update |
| PUT | `/reviews/{review}` | reviews.update | auth | ReviewPolicy@update |
| DELETE | `/reviews/{review}` | reviews.destroy | auth | ReviewPolicy@delete |
| POST | `/reviews/{review}/like` | reviews.like | auth | —（自分のレビューにもいいね可） |
| POST | `/books/{book}/favorites` | favorites.toggle | auth | — |
| GET | `/favorites` | favorites.index | auth | — |
| resource | `/genres` | genres.* | auth | —（Policyなし。ログインユーザーなら誰でも操作可） |
| GET | `/ranking` | ranking.index | — | — |

`/books/create` は `/books/{book}` より先に定義する（resource を使えば自動で正しい順になる）。

---

## 7. 画面ごとに渡すデータ（Blade に合わせる）

| ビュー | 渡す変数 | 取得方法のポイント |
|---|---|---|
| books.index | `$books` | `Book::with('genres')->withAvg('reviews', 'rating')`、新しい順、`paginate(10)` |
| books.show | `$book` | `load(['genres', 'reviews.user', 'reviews.likedByUsers'])`。レビューは新しい順（※並び順は仮決定） |
| books.create | `$genres` | 全ジャンル。**`$book` は渡さない**（Blade が `isset($book)` で登録/編集を判定） |
| books.edit | `$book`, `$genres` | `$bookGenreIds` は Blade 内で作るので渡さない |
| reviews.edit | `$review` | `$review->book` を使う |
| genres.index | `$genres` | `withCount('books')`、ページネーションなし（`get()`） |
| genres.show | `$genre`, `$books` | `$books = $genre->books()->with('genres')->...->paginate(10)` |
| genres.create | — | |
| genres.edit | `$genre` | |
| favorites.index | `$books` | ログインユーザーの `favoriteBooks`、`paginate(10)` |
| ranking.index | `$rankedBooks` | `has('reviews')`＋`withAvg('reviews','rating')`＋`withCount('reviews')`、平均の降順、10件、ページネーションなし |

- **「新しい順」は `orderByDesc('created_at')->orderByDesc('id')`**（Seeder やテストで created_at が同じ秒になるため、id で順番を確定させる）
- 平均評価の表示形式（小数・星）は Blade 側で処理しているので、コントローラーは生の平均値を渡す

---

## 8. 画面遷移とフラッシュメッセージ（確定）

Blade でフラッシュを表示できるのは `books.index`（success）、`books.show`（success）、`genres.index`（success / error）のみ。

| 操作 | 成功時のリダイレクト先 | フラッシュ（キー：文言） |
|---|---|---|
| 書籍登録 | 登録した書籍の詳細 | success：`書籍を登録しました。` |
| 書籍更新 | 更新した書籍の詳細 | success：`書籍情報を更新しました。` |
| 書籍削除 | 書籍一覧 | success：`書籍を削除しました。` |
| レビュー投稿 | 対象書籍の詳細 | success：`レビューを投稿しました。` |
| レビュー更新 | 対象書籍の詳細 | success：`レビューを更新しました。` |
| レビュー削除 | 対象書籍の詳細 | success：`レビューを削除しました。` |
| お気に入り追加/解除 | **操作した画面に戻る**（`redirect()->back()`。書籍詳細・お気に入り一覧の両方にボタンがある） | success：`お気に入りに追加しました。` / `お気に入りを解除しました。`（`toggle()` の戻り値で出し分け） |
| いいね追加/取消 | 対象書籍の詳細 | **メッセージなし** |
| ジャンル登録 | ジャンル一覧 | success：`ジャンルを作成しました。` |
| ジャンル更新 | ジャンル一覧 | success：`ジャンルを更新しました。` |
| ジャンル削除（書籍の紐付けなし） | ジャンル一覧 | success：`ジャンルを削除しました。` |
| ジャンル削除（書籍の紐付けあり） | ジャンル一覧（削除しない） | error：`このジャンルには書籍が紐付いているため削除できません。` |
| 会員登録・ログイン | `/` | なし |
| ログアウト | `/login` | なし |

- バリデーションエラー時は、入力内容を保持して元の入力画面に戻す（レビュー投稿は書籍詳細、レビュー更新はレビュー編集画面）
- 他人の書籍・レビューの編集画面アクセス・更新・削除は **403**（Laravel 標準のエラー画面）

## 9. 認可（Policy）

- **BookPolicy**：`update` / `delete` → `$user->id === $book->user_id`
- **ReviewPolicy**：`update` / `delete` → `$user->id === $review->user_id`
- ジャンル・いいね・お気に入りには Policy を作らない
- 自分が登録した書籍にもレビューできる。同じ書籍に複数レビューできる。自分のレビューにもいいねできる
- 管理者権限は設けない

---

## 10. バリデーション（FormRequest・確定）

メッセージは「〇〇は必須です。」「〇〇は△△で入力してください。」の形で統一する。

### StoreBookRequest / UpdateBookRequest（Web）

| 項目 | ルール | メッセージ |
|---|---|---|
| title | `required\|string\|max:255` | タイトルは必須です。／タイトルは255文字以内で入力してください。 |
| author | `required\|string\|max:255` | 著者は必須です。／著者は255文字以内で入力してください。 |
| isbn | `required\|digits:13\|unique:books,isbn`（更新時は `Rule::unique('books','isbn')->ignore($this->route('book'))`） | ISBNは必須です。／ISBNは13桁で入力してください。／そのISBNは既に使用されています。 |
| published_date | `required\|date`（未来日も可） | 出版日は必須です。／出版日は有効な日付形式で入力してください。 |
| description | `nullable\|string`（**文字数上限なし**） | — |
| image_url | `nullable\|url\|max:255`（形式のみ。存在確認や拡張子チェックはしない） | 画像URLは有効なURL形式で入力してください。／画像URLは255文字以内で入力してください。 |
| genres | `required\|array\|min:1` | ジャンルは1つ以上選択してください。 |
| genres.* | `integer\|distinct\|exists:genres,id` | 選択されたジャンルは存在しません。 |

ISBN は**半角数字13桁のみ**受け付ける（全角数字・ハイフン入りはエラー）。

### StoreReviewRequest / UpdateReviewRequest

| 項目 | ルール | メッセージ |
|---|---|---|
| rating | `required\|integer\|between:1,5` | 評価は必須です。／評価は1〜5の範囲で選択してください。 |
| comment | `required\|string\|max:1000` | コメントは必須です。／コメントは1000文字以内で入力してください。 |

### StoreGenreRequest / UpdateGenreRequest

| 項目 | ルール | メッセージ |
|---|---|---|
| name | `required\|string\|max:255\|unique:genres,name`（更新時は自身を除外） | ジャンル名は必須です。／ジャンル名は255文字以内で入力してください。／そのジャンル名は既に使用されています。 |

（ジャンルの `name` は会員登録の「お名前」と同じキーなので、必ず `messages()` で「ジャンル名」と明示する）

### 公開API（Api\V1）

- **IndexBookRequest**：keyword `nullable|string|max:255`、genre_id `nullable|integer|exists:genres,id`、page `nullable|integer|min:1`、per_page `nullable|integer|between:1,100`
- **StoreBookRequest / UpdateBookRequest**：Web 版と同じルール＋ user_id `required|integer|exists:users,id`（登録者IDは必須です。／指定された登録者は存在しません。）
- エラーメッセージは Web 版と同じ日本語

---

## 11. 公開API（⚠️ 未確定：PMの回答待ち。実装は人間の指示があってから）

設計案（変更の可能性あり）：

- `routes/api.php`、`Route::prefix('v1')` ＋ `apiResource('books', Api\V1\BookController::class)`、基本機能は認証なし
- API Resource（BookResource）で整形し、`data` キーで包む
- 項目：id, title, author, isbn, published_date, description, image_url, average_rating（小数第1位、レビューなしは null）, reviews_count, genres[{id, name}], user{id, name}（**email は含めない**）, created_at, updated_at。詳細は reviews[{id, rating, comment, user{id, name}, created_at}] も含める（新しい順）
- 一覧：デフォルト20件、per_page 1〜100、新しい順、keyword はタイトル OR 著者の部分一致、genre_id で絞り込み（組み合わせ時に OR 条件で絞り込みが外れないよう where をグループ化）
- ステータス：取得200・登録201・更新200・削除204（ボディなし）・存在しない404・バリデーション422
- 404 は `{"message": "指定された書籍が見つかりません。"}`（`app/Exceptions/Handler.php` で変換）
- `Accept: application/json` がなくても JSON で返すミドルウェアを api グループに追加
- 更新は PUT の全体更新（全必須項目が必要）

## 12. テスト（⚠️ 方針はPMの回答待ち）

設計済みのテストケースは別途管理表（BookShelf_ヒアリング管理.xlsx の「テスト設計」タブ、126件）にある。実装時の前提：

- `RefreshDatabase` ＋ Factory で必要なデータを作る（Seeder に依存しない）。BookFactory・GenreFactory・ReviewFactory を作成
- `tests/Unit` のモデルテストは `Tests\TestCase` を継承する（標準の `PHPUnit\Framework\TestCase` では DB が使えない）
- 認可エラーは 403 に加えて DB が変わっていないことを確認する
- 「新しい順」のテストは created_at を明示的にずらして作る
- カバレッジ計測には `.env` に `SAIL_XDEBUG_MODE=develop,debug,coverage` を設定して `sail up -d` し直す。目標は基本機能で 60% 超

## 13. 仮決定（自分の判断で決めたもの。変更の可能性あり）

- ランキングで平均評価が同じ場合：レビュー件数の多い順
- お気に入り一覧：お気に入りに追加した日時の新しい順（`favorites.created_at`）
- 書籍詳細のレビュー一覧：投稿日時の新しい順
- ジャンル一覧：登録順（id 順）
- ジャンル詳細の書籍：新しい順
