<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;


// ID01 一般ユーザー認証機能のテスト
class Id01Test extends TestCase
{

    use RefreshDatabase;
    use WithFaker;

    // ID01-1,2,5 必須項目が空の場合に、日本語のエラーメッセージが返されることをテストします。
    public function test_validation_errors_show_correct_messages(): void
    {
        // 必須項目を空にしてPOSTリクエストを送信
        $response = $this->post('/register', [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください。',
            'email' => 'メールアドレスを入力してください。',
            'password' => 'パスワードを入力してください。',
        ]);
    }


    // ID01-2(追加) メールアドレスの形式が不正な場合に、正しいエラーメッセージが返されることをテストします。
    public function test_invalid_email_format_error(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'invalid-email-format', // 不正なメール形式
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレス形式で入力してください。',
        ]);
    }


    // ID01-3,4 パスワードの最小文字数（8文字）のテストと、パスワード不一致のテスト。
    public function test_password_length_and_confirmation_errors(): void
    {
        // パスワードが短すぎる場合（7文字）のテスト
        $response_short = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test_short@example.com',
            'password' => 'short7', // 7文字
            'password_confirmation' => 'short7',
        ]);

        $response_short->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください。',
        ]);

        // パスワードと確認用パスワードが一致しない場合のテスト
        $response_unmatched = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test_unmatched@example.com',
            'password' => 'password8',
            'password_confirmation' => 'unmatched9',
        ]);

        $response_unmatched->assertSessionHasErrors([
            'password' => 'パスワードと一致しません。',
        ]);
    }


    // ID01-2(追加) 既に登録されているメールアドレスを使用した場合のテスト。
    public function test_unique_email_error(): void
    {
        // 事前にユーザーをデータベースに作成
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        // 同じメールアドレスで登録を試みる
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'existing@example.com', // 既存のメールアドレス
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'このメールアドレスは既に登録されています。',
        ]);
    }


    // ID01-6 有効なデータでユーザー登録が成功し、データベースに保存されることをテストします。
    public function test_successful_registration_and_db_storage(): void
    {
        $userData = [
            'name' => 'Valid User',
            'email' => 'valid_user@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ];

        $response = $this->post('/register', $userData);

        $response->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'name' => 'Valid User',
            'email' => 'valid_user@example.com',
        ]);

        $this->assertCount(1, User::all());
    }
}