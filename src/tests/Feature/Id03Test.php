<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;


// ID03 管理者ログイン認証機能のテスト
class Id03Test extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * ゲストユーザーが管理者ログインページにアクセスできることを確認します。
     */
    public function test_guest_can_access_admin_login_page(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    // --- バリデーションエラーテスト (LoginRequestに基づく) ---

    /**
     * @test
     * 管理者ログイン時にメールアドレスが未入力の場合、エラーが表示されることを確認します。
     */
    public function test_admin_login_fails_without_email_shows_required_error(): void
    {
        // $this->from('/admin/login') を追加し、リダイレクト先を明示的に指定
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => '', // 未入力
            'password' => 'testpassword',
        ]);

        // 失敗時はリダイレクト（302）し、セッションにエラーメッセージがあることを確認
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください。',
        ]);
    }

    /**
     * @test
     * 管理者ログイン時にパスワードが未入力の場合、エラーが表示されることを確認します。
     */
    public function test_admin_login_fails_without_password_shows_required_error(): void
    {
        // $this->from('/admin/login') を追加し、リダイレクト先を明示的に指定
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'test@example.com',
            'password' => '', // 未入力
        ]);

        // 失敗時はリダイレクト（302）し、セッションにエラーメッセージがあることを確認
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください。',
        ]);
    }
    
    // --- 認証ロジックテスト (LoginControllerに基づく) ---

    /**
     * @test
     * ログイン情報が一致しない場合にコントローラで定義されたエラーメッセージが表示されることを確認します。
     */
    public function test_admin_login_with_invalid_credentials_shows_error(): void
    {
        // 1. 存在しないユーザーでログインを試みる
        $response = $this->post('/admin/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any_password',
        ]);

        // 2. 検証
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertRedirect('/admin/login');
        // コントローラで設定された認証失敗時のエラーメッセージを確認
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }

    /**
     * @test
     * 一般ユーザーが管理者ログインページからログインできないことを確認します。
     * (ロールチェックのテスト)
     */
    public function test_general_user_cannot_login_via_admin_page(): void
    {
        // 1. 一般ユーザーをセットアップ
        $password = 'general_pass';
        $generalUser = User::factory()->create([
            'email' => 'general@example.com',
            'password' => Hash::make($password),
            'role' => 'general', // 一般ユーザーロール
        ]);

        // 2. 管理者ログインURLでログインを試みる
        $response = $this->post('/admin/login', [
            'email' => $generalUser->email,
            'password' => $password,
        ]);

        // 3. 検証
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertRedirect('/admin/login');
        // コントローラで設定されたロール不一致時のエラーメッセージを確認
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }

    /**
     * @test
     * 正常な管理者ユーザーのログインと、管理者ダッシュボードへのリダイレクトを確認します。
     */
    public function test_admin_user_can_login_and_redirects_to_admin_dashboard(): void
    {
        // 1. 管理者ユーザーをセットアップ
        $password = 'admin_password';
        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        // 2. ログイン実行
        $response = $this->post('/admin/login', [
            'email' => $adminUser->email,
            'password' => $password,
        ]);

        // 3. 検証
        $response->assertStatus(302);
        $this->assertAuthenticatedAs($adminUser);
        // 成功時のリダイレクト先を確認
        $response->assertRedirect('/admin/attendance/list');
    }
}