<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;


// ID06 出動（一般ユーザー）機能のテスト
class Id06Test extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザーが未出勤の状態（初期状態）で出勤ページにアクセスすると
     * 「出勤」ボタンが表示されることをテストする。
     */
    public function test_user_sees_clock_in_button_when_not_clocked_in()
    {
        // テスト日時を固定
        Carbon::setTestNow(Carbon::today());
        $today = Carbon::today()->toDateString();
        
        // 認証済みのメール認証済みユーザーを作成
        $user = User::factory()->create([
            'email_verified_at' => now(), // Bladeの条件を満たすため
        ]);

        // その日の勤怠レコードが存在しないことを確認（初期状態）
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'checkin_date' => $today,
        ]);

        // ユーザーとしてログインして出勤ページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // ステータスコードが200（成功）であることを確認
        $response->assertStatus(200);

        // ビューが「勤務外」ステータスを表示していることを確認
        $response->assertSee('勤務外');

        // 「出勤」ボタンが表示されていることを確認
        $response->assertSee('出勤');
        
        // 「休憩戻」や「退勤」ボタンが表示されていないことを確認
        $response->assertDontSee('休憩戻');
        $response->assertDontSee('退勤');
        $response->assertDontSee('休憩入');
    }

    /**
     * ユーザーが出勤ボタンを押す（POSTリクエスト）ことで、
     * 勤怠レコードが作成され、勤務中状態になることをテストする。
     */
    public function test_user_can_clock_in()
    {
        // テスト日時を固定
        $now = Carbon::create(2025, 1, 15, 9, 0, 0);
        Carbon::setTestNow($now);
        $today = $now->toDateString();
        $clockInTime = $now->toDateTimeString();

        // 認証済みのメール認証済みユーザーを作成
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // ユーザーとしてログインして出勤打刻ルートにPOSTリクエストを送信
        $response = $this->actingAs($user)->post(route('attendance.clock_in'));

        // リダイレクトが発生することを確認
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // データベースに新しい勤怠レコードが作成されたことを確認
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'checkin_date' => $today,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => null,
        ]);
        
        // 打刻後のページ（/attendance）にアクセスし、「勤務中」ステータスが表示されていることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('勤務中');
        $response->assertDontSee('出勤');
        $response->assertSee('退勤'); // 勤務中なので退勤ボタンが表示される
        $response->assertSee('休憩入'); // 勤務中なので休憩入ボタンが表示される
    }
    
    /**
     * 出勤打刻後、勤怠一覧ページで出勤時刻が正しく表示されることをテストする。
     */
    public function test_clocked_in_time_is_correctly_displayed_on_month_attendance_page()
    {
        // 1. テスト日時を固定（2025年2月10日 9:15）
        $now = Carbon::create(2025, 2, 10, 9, 15, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);
        $today = $now->toDateString();
        
        // 2. 認証済みユーザーを作成
        $user = User::factory()->create(['email_verified_at' => now()]);

        // 3. 出勤打刻を実行 (attendance.clock_in ルートを使用)
        $this->actingAs($user)->post(route('attendance.clock_in'));
        
        // データベースに出勤レコードが作成されたことを確認
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'checkin_date' => $today,
            'clock_in_time' => $now->toDateTimeString(),
        ]);

        // 4. 勤怠一覧ページにアクセス (2025年2月を指定)
        // 💡 修正箇所: ルート名を 'user.month.index' に変更
        $response = $this->actingAs($user)->get(route('user.month.index', ['year' => 2025, 'month' => 2]));

        // 5. 検証
        $response->assertStatus(200);

        // 期待される表示時刻 (H:i 形式)
        $expectedClockInTime = '09:15';
        
        // 期待される日付表示 (2月10日(月))
        $expectedDayLabel = '2/10(月)';

        // ページ全体で日付ラベルと出勤時刻の組み合わせが確認できること
        $response->assertSeeInOrder([
            $expectedDayLabel,      // 日付
            $expectedClockInTime    // 出勤時刻
        ]);
        
        // 6. テスト終了後、固定時刻設定を解除
        Carbon::setTestNow(null);
    }

    /**
     * 退勤済みユーザーがログインした時、出勤ボタンが表示されず
     * 「お疲れ様でした。」のメッセージが表示されることをテストする。
     */
    public function test_user_sees_finished_message_when_clocked_out()
    {
        // テスト日時を固定
        Carbon::setTestNow(Carbon::today()->endOfDay()->subMinute()); // 今日の終わり近くに設定
        $today = Carbon::today()->toDateString();
        
        // 認証済みのメール認証済みユーザーを作成
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 退勤済みの勤怠レコードを作成
        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $today,
            'clock_in_time' => Carbon::today()->setHour(9)->setMinute(0),
            'clock_out_time' => Carbon::today()->setHour(18)->setMinute(0), // 退勤済み
        ]);

        // ユーザーとしてログインして出勤ページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // ステータスコードが200（成功）であることを確認
        $response->assertStatus(200);

        // ビューが「退勤済」ステータスを表示していることを確認
        $response->assertSee('退勤済');
        
        // 「お疲れ様でした。」メッセージが表示されていることを確認
        $response->assertSee('お疲れ様でした。');

        // 出勤ボタンが表示されていないことを確認
        $response->assertDontSee('出勤');
        
        // その他のボタンも表示されていないことを確認
        $response->assertDontSee('休憩入');
        $response->assertDontSee('休憩戻');
    }
}