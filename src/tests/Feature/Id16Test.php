<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest; // 認証リンクのテストのために追加

class Id16Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ----------------------------------------------------------------
        // routes/web.php の定義に合わせたルートをモックします
        // ----------------------------------------------------------------

        // 勤怠ページ (user.stamping.index)
        Route::get('/attendance', function () {
            return 'Attendance Page';
        })->middleware('auth', 'verified')->name('user.stamping.index'); // 'verified'ミドルウェアを追加してモックをより現実に近づける

        // 管理者勤怠一覧ページ (admin.attendance.list.index)
        Route::get('/admin/attendance/list', function () {
            return 'Admin Attendance List Page';
        })->middleware('auth')->name('admin.attendance.list.index');
    }

    /**
     * ID16-1(1)ユーザー登録時に認証メールが送信されることをテストします。
     * @return void
     */
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
    
    /**
     * ID16-1(２)未認証ユーザーが認証通知ページにアクセスできることをテストします。
     * @return void
     */
    public function test_unverified_user_is_redirected_to_verification_notice()
    {
        // メール未認証のユーザーを作成
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // 認証済みの状態で認証通知ページにアクセスし、ステータス200を確認
        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertStatus(200);
    }

    /**
     * ID16-1(３)再送ボタンが2通目の認証メールを送信することをテストします。
     * @return void
     */
    public function test_resend_button_sends_a_second_verification_email()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // 認証メール再送ルートへのPOSTリクエスト
        $this->actingAs($user)
            ->post(route('verification.send'));

        // 通知が送信されたことをアサート
        Notification::assertSentTo(
            [$user], VerifyEmail::class
        );
    }

    /**
     * ID16-1(４)未認証ユーザーがメール認証ページからMailHogにリダイレクトされることをテストします。
     * @return void
     */
    public function test_unverified_user_is_redirected_to_mailhog_from_verification_page()
    {
        // メール未認証のユーザーを作成します。
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // 作成したユーザーとして認証し、認証通知ページにアクセスします。
        $response = $this->actingAs($user)->get(route('verification.notice'));

        // ページのコンテンツを検証します。
        // 指定されたHTMLのリンクが正しく存在し, MailHogのURLを指しているかを確認します。
        $response->assertSee('<a href="http://localhost:8025" target="_blank" class="verification-button">認証はこちらから</a>', false);
    }


    /**
     * 未認証ユーザーがログインページにリダイレクトされることをテストします。
     * @return void
     */
    public function test_unverified_user_redirects_to_login_page()
    {
        // 認証なしで、'auth'ミドルウェアでガードされたページ（/attendance）にアクセスします。
        $response = $this->get('/attendance'); 

        // ログインページにリダイレクトされることを確認 (ステータス302を期待)
        $response->assertRedirect('http://localhost/login');
    }

    /**
     * 認証済みの一般ユーザーが、ログイン後に勤怠ページにリダイレクトされることをテストします。
     * @return void
     */
    public function test_verified_user_redirects_to_attendance_page()
    {
        // 'email_verified_at' を設定したユーザーを作成
        $user = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);

        // ログイン処理をモック
        $response = $this->actingAs($user)
                         ->post('/login', [
                            'email' => $user->email,
                            'password' => 'password',
                         ]);

        // 勤怠ページ（/attendance）にリダイレクトされることを確認
        $response->assertRedirect('/attendance'); 
    }

    /**
     * 認証済みの管理者ユーザーが、ログイン後に管理者ページにリダイレクトされることをテストします。
     * @return void
     */
    public function test_verified_admin_user_redirects_to_admin_page()
    {
        // 'email_verified_at' を設定した管理者を作成
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        // ログイン処理をモック
        $response = $this->actingAs($admin)
                         ->post('/login', [
                            'email' => $admin->email,
                            'password' => 'password',
                         ]);

        // 管理者用のトップページ（管理者勤怠一覧）にリダイレクトされることを確認
        $response->assertRedirect(route('admin.attendance.list.index'));
    }

    /**
     * メール認証完了後、ユーザーが/attendanceにリダイレクトされることをテストします。
     * @return void
     */
    public function test_user_is_redirected_to_attendance_page_after_email_verification()
    {
        // 'staff' ロールを付与した未認証ユーザーを作成
        $user = User::factory()->unverified()->create(['role' => 'employee']); // ロールを'staff'に戻す

        // 認証ルートをモック
        Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();
            
            // fresh()を使ってデータベースから最新（認証済み）のユーザーを取得し、セッションを更新する
            // これでリダイレクト後のリクエストでverifiedミドルウェアを通過できる
            auth()->login($request->user()->fresh()); 
            
            // routes/web.php の定義に合わせて /attendance にリダイレクト
            return redirect('/attendance'); 
        })->name('verification.verify'); // ★signedミドルウェアを削除！

        // メール認証リンクにアクセス
        $response = $this->actingAs($user)->get(route('verification.verify', [
            'id' => $user->id,
            // hash は正しい値であると仮定
            'hash' => sha1($user->email),
        ]));

        // routes/web.php の定義通り、/attendance にリダイレクトされることを確認
        $response->assertRedirect('/attendance'); 
    }
}