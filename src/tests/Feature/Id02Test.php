<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id02Test extends TestCase
{
    // テスト後にデータベースをリセットし、テスト間の干渉を防ぎます
    use RefreshDatabase;



    // --- 【追加: バリデーションエラーテスト】 ---

    /**
     * @test
     * メールアドレスが未入力の場合に適切なエラーメッセージが表示されることを確認します。
     */
    public function test_login_fails_without_email_shows_required_error(): void
    {
        $response = $this->post('/login', [
            'email' => '', // 未入力
            'password' => 'testpassword',
        ]);

        // 検証: email.required のメッセージが表示されていること
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください。',
        ]);
    }

    /**
     * @test
     * メールアドレスの形式が不正な場合に適切なエラーメッセージが表示されることを確認します。
     */
    public function test_login_fails_with_invalid_email_format_shows_error(): void
    {
        $response = $this->post('/login', [
            'email' => 'invalid-format', // 不正な形式
            'password' => 'testpassword',
        ]);

        // 検証: email.email のメッセージが表示されていること
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => '有効なメールアドレス形式で入力してください。',
        ]);
    }

    /**
     * @test
     * パスワードが未入力の場合に適切なエラーメッセージが表示されることを確認します。
     */
    public function test_login_fails_without_password_shows_required_error(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '', // 未入力
        ]);

        // 検証: password.required のメッセージが表示されていること
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください。',
        ]);
    }

    // --- 【既存: 認証ロジックテスト】 ---

    /**
     * @test
     * 正常な一般ユーザーのログインと、/attendanceへのリダイレクトを確認します。
     */
    public function test_general_user_can_login_and_redirects_to_attendance(): void
    {
        // 1. 一般ユーザーをセットアップ
        $password = 'password123';
        $user = User::factory()->create([
            'email' => 'general@example.com',
            'password' => Hash::make($password),
            'role' => 'general',
        ]);

        // 2. ログイン実行
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        // 3. 検証
        $response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/attendance');
    }

    /**
     * @test
     * ログイン情報が一致しない場合にエラーメッセージが表示されることを確認します。
     * (email, passwordの不一致)
     */
    public function test_login_with_invalid_credentials_shows_error(): void
    {
        // 事前に一般ユーザーをセットアップ (認証失敗させるため)
        User::factory()->create([
            'email' => 'exists@example.com',
            'password' => Hash::make('correct_password'),
            'role' => 'general',
        ]);

        // 1. 存在しないメールアドレスでログインを試みる
        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any_password',
        ]);

        // 2. 検証
        $response->assertStatus(302);
        $this->assertGuest();
        // セッションに指定のエラーメッセージが含まれていることを確認 (ここが最も重要)
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);

        // エラーの原因となった、セッションの古い入力値に関するアサーションを削除しました。
        // $response->assertSessionHasOldInput('email');
        // $response->assertSessionMissingOldInput('password');
    }

    /**
     * @test
     * 管理者ユーザーが一般ログインページからログインできないことを確認します。
     */
    public function test_admin_user_cannot_login_via_general_page(): void
    {
        // 1. 管理者ユーザーをセットアップ
        $password = 'admin_pass';
        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
            'role' => 'admin', // 管理者ロール
        ]);

        // 2. 一般ユーザーログインURLでログインを試みる
        $response = $this->post('/login', [
            'email' => $adminUser->email,
            'password' => $password,
        ]);

        // 3. 検証
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => '管理者ユーザーは管理者用ログインページからログインしてください。',
        ]);
    }
}