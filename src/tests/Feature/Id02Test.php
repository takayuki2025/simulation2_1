<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;


// ID02 一般ユーザーログイン認証機能のテスト
class Id02Test extends TestCase
{

    use RefreshDatabase;

    // ID02-1 メールアドレスが未入力の場合に適切なエラーメッセージが表示されることを確認します。
    public function test_login_fails_without_email_shows_required_error(): void
    {
        $response = $this->post('/login', [
            'email' => '', // 未入力
            'password' => 'testpassword',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください。',
        ]);
    }

    // ID02-1(追加) メールアドレスの形式が不正な場合に適切なエラーメッセージが表示されることを確認します。
    public function test_login_fails_with_invalid_email_format_shows_error(): void
    {
        $response = $this->post('/login', [
            'email' => 'invalid-format', // 不正な形式
            'password' => 'testpassword',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => '有効なメールアドレス形式で入力してください。',
        ]);
    }

    // ID02-2 パスワードが未入力の場合に適切なエラーメッセージが表示されることを確認します。
    public function test_login_fails_without_password_shows_required_error(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください。',
        ]);
    }

    // ID02-3 ログイン情報が一致しない場合にエラーメッセージが表示されることを確認します。
    public function test_login_with_invalid_credentials_shows_error(): void
    {
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

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }

    // ID02(追加) 正常な一般ユーザーのログインと、/attendanceへのリダイレクトを確認します。
    public function test_general_user_can_login_and_redirects_to_attendance(): void
    {
        $password = 'password123';
        $user = User::factory()->create([
            'email' => 'general@example.com',
            'password' => Hash::make($password),
            'role' => 'employee',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/attendance');
    }

    // ID02(追加) 管理者ユーザーが一般ログインページからログインできないことを確認します。
    public function test_admin_user_cannot_login_via_general_page(): void
    {
        $password = 'admin_pass';
        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        // 一般ユーザーログインURLでログインを試みる
        $response = $this->post('/login', [
            'email' => $adminUser->email,
            'password' => $password,
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }
}