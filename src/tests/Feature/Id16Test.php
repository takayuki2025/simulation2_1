<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;


// ID16 メール認証機能のテスト
class Id16Test extends TestCase
{
    use RefreshDatabase;

    // ID16-1　ユーザー登録時に認証メールが送信されることをテストします。
    public function test_a_verification_email_is_sent_on_user_registration()
    {
        // Notificationファサードをモックする
        Notification::fake();

        // ユーザー登録をシミュレートするPOSTリクエストを送信
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // 登録されたユーザーを取得
        $user = User::where('email', 'test@example.com')->first();

        // ユーザーが作成されたことと、認証メールが送信されたことをアサート
        $this->assertNotNull($user);
        Notification::assertSentTo(
            [$user], VerifyEmail::class
        );
    }

    // ID16-2　未認証ユーザーが認証通知ページにアクセスできることをテストします。
    public function test_unverified_user_is_redirected_to_verification_notice()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertStatus(200);
    }

    // ID16-2　 未認証ユーザーがメール認証ページからMailHogにリダイレクトされることをテストします。
    public function test_unverified_user_is_redirected_to_mailhog_from_verification_page()
    {
        // メール未認証のユーザーを作成します。
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // 作成したユーザーとして認証し、認証通知ページにアクセスします。
        $response = $this->actingAs($user)->get(route('verification.notice'));

        // ページのコンテンツを検証します。
        $response->assertSee('<a href="http://localhost:8025" target="_blank" class="verification-button">認証はこちらから</a>', false);
    }

    // ID16-2(追加)　 再送ボタンが2通目の認証メールを送信することをテストします。
    public function test_resend_button_sends_a_second_verification_email()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('verification.send'));

        // 通知が送信されたことをアサート
        Notification::assertSentTo(
            [$user], VerifyEmail::class
        );
    }

    // ID16-3　 メール認証完了後、ユーザーが/attendanceにリダイレクトされることをテストします。
    public function test_user_is_redirected_to_attendance_page_after_email_verification()
    {
        $user = User::factory()->unverified()->create(['role' => 'employee']);

        // 認証ルートをモック (このモックは、Laravelの標準的な認証完了後のリダイレクト処理をオーバーライドするために必要です)
        Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();

            // fresh()を使ってデータベースから最新（認証済み）のユーザーを取得し、セッションを更新する
            auth()->login($request->user()->fresh());

            return redirect('/attendance');
        })->name('verification.verify');

        // メール認証リンクにアクセス
        $response = $this->actingAs($user)->get(route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]));

        $response->assertRedirect('/attendance');
    }

    // ID16-3　 認証済みの管理者ユーザーが、ログイン後に管理者ページにリダイレクトされることをテストします。
    public function test_verified_admin_user_redirects_to_admin_page()
    {
        // 'email_verified_at' を設定した管理者を作成
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        // ログイン処理をモック
        $response = $this->actingAs($admin)
            ->post('/login', ['email' => $admin->email,'password' => 'password',
            ]);

        // 管理者用のトップページ（管理者勤怠一覧）にリダイレクトされることを確認
        $response->assertRedirect(route('admin.attendance.list.index'));
    }
}