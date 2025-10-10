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

    // ID06-1(1) ユーザーが未出勤の状態（初期状態）で出勤ページにアクセスすると「出勤」ボタンが表示されることをテストする。
    public function test_user_sees_clock_in_button_when_not_clocked_in()
    {
        Carbon::setTestNow(Carbon::today());
        $today = Carbon::today()->toDateString();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'checkin_date' => $today,
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);

        $response->assertSee('勤務外');
        $response->assertSee('出勤');
        $response->assertDontSee('休憩戻');
        $response->assertDontSee('退勤');
        $response->assertDontSee('休憩入');
    }

    // ID06-1(2) ユーザーが出勤ボタンを押す（POSTリクエスト）ことで勤怠レコードが作成され、勤務中状態になることをテストする。
    public function test_user_can_clock_in()
    {
        $now = Carbon::create(2025, 1, 15, 9, 0, 0);
        Carbon::setTestNow($now);
        $today = $now->toDateString();
        $clockInTime = $now->toDateTimeString();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('attendance.clock_in'));
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'checkin_date' => $today,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('出勤中');
        $response->assertSee('退勤');
        $response->assertSee('休憩入');
    }

    // ID06-2 退勤済みユーザーがログインした時、出勤ボタンが表示されず(出勤は1日一回)で「お疲れ様でした。」のメッセージが表示されることをテストする。
    public function test_user_sees_finished_message_when_clocked_out()
    {
        Carbon::setTestNow(Carbon::today()->endOfDay()->subMinute());
        $today = Carbon::today()->toDateString();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $today,
            'clock_in_time' => Carbon::today()->setHour(9)->setMinute(0),
            'clock_out_time' => Carbon::today()->setHour(18)->setMinute(0),
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);

        $response->assertSee('退勤済');
        $response->assertSee('お疲れ様でした。');
        $response->assertDontSee('出勤');
        $response->assertDontSee('休憩入');
        $response->assertDontSee('休憩戻');
    }

    // ID06-3 出勤打刻後、勤怠一覧ページで出勤時刻が正しく表示されることをテストする。
    public function test_clocked_in_time_is_correctly_displayed_on_month_attendance_page()
    {
        $now = Carbon::create(2025, 2, 10, 9, 15, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);
        $today = $now->toDateString();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post(route('attendance.clock_in'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'checkin_date' => $today,
            'clock_in_time' => $now->toDateTimeString(),
        ]);

        $response = $this->actingAs($user)->get(route('user.month.index', ['year' => 2025, 'month' => 2]));
        $response->assertStatus(200);

        $expectedClockInTime = '09:15';
        $expectedDayLabel = '2/10(月)';

        $response->assertSeeInOrder([
            $expectedDayLabel,
            $expectedClockInTime
        ]);

        Carbon::setTestNow(null);
    }
}