<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 認証（会員登録・ログイン・ログアウト）のテスト（ケースID A1〜A11）。
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 会員登録
    // ------------------------------------------------------------------

    /**
     * A1【会員登録】会員登録できる
     */
    public function test_a1_user_can_register(): void
    {
        $response = $this->post(route('register'), $this->validRegisterData());

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => '登録 太郎',
            'email' => 'register@example.com',
        ]);
        $this->assertDatabaseCount('users', 1);

        // パスワードは平文で保存されず、ハッシュ化されている
        $user = User::where('email', 'register@example.com')->firstOrFail();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * A2【会員登録】必須項目が空だと登録できない
     */
    public function test_a2_register_requires_required_fields(): void
    {
        $this->post(route('register'), [])
            ->assertSessionHasErrors([
                'name' => 'お名前は必須です。',
                'email' => 'メールアドレスは必須です。',
                'password' => 'パスワードは必須です。',
            ]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * A3【会員登録】メールアドレスの形式が不正・登録済みだと登録できない
     */
    #[DataProvider('invalidEmailProvider')]
    public function test_a3_register_rejects_invalid_or_duplicate_email(string $email, string $message): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->post(route('register'), $this->validRegisterData(['email' => $email]))
            ->assertSessionHasErrors(['email' => $message]);

        $this->assertGuest();
        // 登録済みの1件（existing@example.com）から増えていない
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            '形式不正' => ['not-an-email', 'メールアドレスは有効なメールアドレス形式で入力してください。'],
            '登録済み' => ['existing@example.com', 'そのメールアドレスは既に使用されています。'],
        ];
    }

    /**
     * A4【会員登録】パスワードが8文字未満・確認用と一致しないと登録できない
     */
    #[DataProvider('invalidPasswordProvider')]
    public function test_a4_register_rejects_invalid_password(string $password, string $confirmation, string $message): void
    {
        $this->post(route('register'), $this->validRegisterData([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ]))->assertSessionHasErrors(['password' => $message]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidPasswordProvider(): array
    {
        return [
            '7文字' => ['pass123', 'pass123', 'パスワードは8文字以上で入力してください。'],
            '確認用と不一致' => ['password123', 'password456', 'パスワードと確認用の入力が一致しません。'],
        ];
    }

    // ------------------------------------------------------------------
    // ログイン
    // ------------------------------------------------------------------

    /**
     * A5【ログイン】ログインできる
     */
    public function test_a5_user_can_login(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);

        $this->post(route('login'), ['email' => 'login@example.com', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    /**
     * A6【ログイン】パスワードが違うとログインできない
     */
    public function test_a6_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'login@example.com']);

        $this->from(route('login'))
            ->post(route('login'), ['email' => 'login@example.com', 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません。']);

        $this->assertGuest();
    }

    /**
     * A7【ログイン】登録されていないメールアドレスではログインできない
     */
    public function test_a7_login_fails_with_unregistered_email(): void
    {
        User::factory()->create(['email' => 'login@example.com']);

        $this->from(route('login'))
            ->post(route('login'), ['email' => 'unknown@example.com', 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません。']);

        $this->assertGuest();
    }

    /**
     * A8【ログイン】メールアドレス・パスワードが空だとログインできない
     */
    public function test_a8_login_requires_email_and_password(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'メールアドレスは必須です。',
                'password' => 'パスワードは必須です。',
            ]);

        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // ログアウト
    // ------------------------------------------------------------------

    /**
     * A9【ログアウト】ログアウトできる
     */
    public function test_a9_user_can_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // 共通
    // ------------------------------------------------------------------

    /**
     * A10【共通】ログイン済みで /login・/register にアクセスすると / へリダイレクトされる
     */
    public function test_a10_logged_in_user_is_redirected_from_login_and_register(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/');
        $this->actingAs($user)->get('/register')->assertRedirect('/');
    }

    /**
     * A11【共通】ログイン中はヘッダーにユーザー名とログアウトが表示される
     */
    public function test_a11_header_shows_user_name_and_logout_when_logged_in(): void
    {
        $user = User::factory()->create(['name' => 'ヘッダー 太郎']);

        // ゲストにはユーザー名もログアウトも表示されない
        $this->get('/')
            ->assertOk()
            ->assertDontSee('ヘッダー 太郎')
            ->assertDontSee('ログアウト');

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('ヘッダー 太郎')
            ->assertSee('ログアウト');
    }

    /**
     * 会員登録のリクエストデータ（有効な値）を返す。
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validRegisterData(array $overrides = []): array
    {
        return array_merge([
            'name' => '登録 太郎',
            'email' => 'register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }
}
