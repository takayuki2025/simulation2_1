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

    // ID03-1 管理者ログイン時にメールアドレスが未入力の場合、エラーが表示されることを確認します。
    public function test_admin_login_fails_without_email_shows_required_error(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => '', // 未入力
            'password' => 'testpassword',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください。',
        ]);
    }

    // ID03-2 管理者ログイン時にパスワードが未入力の場合、エラーが表示されることを確認します。
    public function test_admin_login_fails_without_password_shows_required_error(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'test@example.com',
            'password' => '', // 未入力
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください。',
        ]);
    }

    // ID03-3 ログイン情報が一致しない場合にコントローラで定義されたエラーメッセージが表示されることを確認します。
    public function test_admin_login_with_invalid_credentials_shows_error(): void
    {
        // 存在しないユーザーでログインを試みる
        $response = $this->post('/admin/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any_password',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }

    // ID03(追加) ゲストユーザーが管理者ログインページにアクセスできることを確認します。
    public function test_guest_can_access_admin_login_page(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    // ID03(追加) 一般ユーザーが管理者ログインページからログインできないことを確認します。
    public function test_general_user_cannot_login_via_admin_page(): void
    {
        $password = 'employee_pass';
        $generalUser = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => Hash::make($password),
            'role' => 'employee', // 一般ユーザーロール
        ]);

        // 管理者ログインURLでログインを試みる
        $response = $this->post('/admin/login', [
            'email' => $generalUser->email,
            'password' => $password,
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertRedirect('/admin/login');
        // コントローラで設定されたロール不一致時のエラーメッセージを確認
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }

    // ID03(追加) 正常な管理者ユーザーのログインと、管理者ダッシュボードへのリダイレクトを確認します。
    public function test_admin_user_can_login_and_redirects_to_admin_dashboard(): void
    {
        $password = 'admin_password';
        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $response = $this->post('/admin/login', [
            'email' => $adminUser->email,
            'password' => $password,
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticatedAs($adminUser);
        $response->assertRedirect('/admin/attendance/list');
    }
}