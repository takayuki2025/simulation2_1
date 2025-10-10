<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;


// ID07 休憩（一般ユーザー）機能のテスト
class Id07Test extends TestCase
{
    use RefreshDatabase;

    // ID07-1 休憩ボタンが正しく機能する（休憩開始し、UIが正しく「休憩中」に更新されること）のテスト。
    public function test_user_can_start_break_and_ui_updates()
    {
        $fixedNow = Carbon::create(2025, 9, 29, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixedNow);
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);
        $clockInTime = $fixedNow->copy()->subHour();
        $todayDate = $fixedNow->toDateString();

        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $todayDate,
            'clock_in_time' => $clockInTime->toDateTimeString(),
            'checkout_time' => null,
            'break_time' => '[]',
            'break_total_time' => 0,
        ]);

        $this->get(route('user.stamping.index'))
            ->assertStatus(200)
            ->assertSee('出勤中')
            ->assertSee('休憩入')
            ->assertDontSee('休憩戻');

        $this->post(route('attendance.break_start'))
            ->assertRedirect(route('user.stamping.index'));

        $this->get(route('user.stamping.index'))
            ->assertStatus(200)
            ->assertSee('休憩中')
            ->assertSee('休憩戻')
            ->assertDontSee('休憩入');

        Carbon::setTestNow();
    }

    // ID07-3 休憩戻るボタンが正しく機能する（休憩開始→休憩終了のサイクル全体と、それに伴うUIの更新）のテスト。
    public function test_user_can_start_and_end_break_cycle()
    {
        $initialTime = Carbon::create(2025, 9, 29, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($initialTime);
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);
        $clockInTime = $initialTime->copy()->subHour();
        $todayDate = $initialTime->toDateString();

        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $todayDate,
            'clock_in_time' => $clockInTime->toDateTimeString(),
            'checkout_time' => null,
            'break_time' => '[]',
            'break_total_time' => 0,
        ]);

        $this->post(route('attendance.break_start'));
        $this->get(route('user.stamping.index'))->assertSee('休憩戻');

        $breakEndTime = $initialTime->copy()->addMinutes(30);
        Carbon::setTestNow($breakEndTime);

        $this->post(route('attendance.break_end'));

        $this->get(route('user.stamping.index'))
            ->assertStatus(200)
            ->assertSee('出勤中')
            ->assertSee('休憩入')
            ->assertDontSee('休憩戻');

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('checkin_date', $todayDate)
            ->first();
        $breakTimes = $attendance->break_time;
        $this->assertCount(1, $breakTimes);
        $this->assertEquals($breakEndTime->toDateTimeString(), $breakTimes[0]['end']);

        Carbon::setTestNow();
    }

    // ID07-2,4 休憩の開始と終了をループで複数回繰り返し、UIとDBの状態が常に正しいことを動的にテストします。
    public function test_dynamic_break_cycles_verify_button_toggling()
    {
        $cycles = 5;
        $breakDurationMinutes = 10;
        $workDurationMinutes = 10;
        $initialTime = Carbon::create(2025, 9, 29, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($initialTime); // 09:00:00
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);
        $todayDate = $initialTime->toDateString();

        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $todayDate,
            'clock_in_time' => $initialTime->toDateTimeString(),
            'checkout_time' => null,
            'break_time' => '[]',
            'break_total_time' => 0,
        ]);

        // 最初の休憩サイクル開始時刻 (10:00:00からスタート)
        $currentTime = $initialTime->copy()->addHour(); // 10:00:00

        // 5回 の休憩サイクルをループで実行
        for ($i = 0; $i < $cycles; $i++) {

            Carbon::setTestNow($currentTime);
            $breakStartTime = $currentTime->copy();

            $this->post(route('attendance.break_start'))
                ->assertRedirect(route('user.stamping.index'));

            $this->get(route('user.stamping.index'))
                ->assertSee('休憩中')
                ->assertSee('休憩戻')
                ->assertDontSee('休憩入', '休憩中は休憩入ボタンが表示されないことを保証します。');

            // 休憩時間を進める
            $breakEndTime = $currentTime->copy()->addMinutes($breakDurationMinutes);
            Carbon::setTestNow($breakEndTime);

            $this->post(route('attendance.break_end'))
                ->assertRedirect(route('user.stamping.index'));

            $this->get(route('user.stamping.index'))
                ->assertSee('出勤中')
                ->assertSee('休憩入')
                ->assertDontSee('休憩戻', '出勤中は休憩戻ボタンが表示されないことを保証します。');

            // 次のサイクルの開始時刻を計算（勤務時間を進める）
            $currentTime = $breakEndTime->copy()->addMinutes($workDurationMinutes);

            // データベース検証 (毎回、最後の休憩レコードが終了していることを確認)
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('checkin_date', $todayDate)
                ->first();
            $breakTimes = $attendance->break_time;

            // 休憩レコードの数が現在のサイクル数と一致すること
            $this->assertCount($i + 1, $breakTimes, "【{$i}回目】休憩レコードの数が不正です。");

            // 最後に終了した休憩の時刻が正しいこと
            $latestBreak = $breakTimes[$i];
            $this->assertEquals($breakStartTime->toDateTimeString(), $latestBreak['start'], "【{$i}回目】休憩開始時刻が不正です。");
            $this->assertEquals($breakEndTime->toDateTimeString(), $latestBreak['end'], "【{$i}回目】休憩終了時刻が不正です。");
        }

        Carbon::setTestNow();
    }

    // ID07-5 休憩時刻が勤怠一覧画面で確認できる（複数回の休憩後月次勤怠リスト（user_month_index）ページで総休憩時間が正しく表示される）ことのテスト。
    public function test_total_break_time_is_displayed_on_monthly_list()
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);

        $initialTime = Carbon::create(2025, 9, 29, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($initialTime); // 09:00:00 出勤
        $todayDate = $initialTime->toDateString();

        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $todayDate,
            'clock_in_time' => $initialTime->toDateTimeString(),
            'checkout_time' => null,
            'break_time' => '[]',
            'break_total_time' => 0,
        ]);

        // 複数回休憩サイクルを実行し、合計25分の休憩を取得 ---
        // --- 1回目の休憩 (15分) ---
        Carbon::setTestNow($initialTime->copy()->addHour()); // 10:00:00
        $this->post(route('attendance.break_start'));

        Carbon::setTestNow($initialTime->copy()->addHour()->addMinutes(15)); // 10:15:00
        $this->post(route('attendance.break_end'));

        // --- 2回目の休憩 (10分) ---
        Carbon::setTestNow($initialTime->copy()->addHours(2)); // 11:00:00
        $this->post(route('attendance.break_start'));

        Carbon::setTestNow($initialTime->copy()->addHours(2)->addMinutes(10)); // 11:10:00
        $this->post(route('attendance.break_end'));

        // 期待される総休憩時間 (15分 + 10分 = 25分)
        $expectedTotalBreakMinutes = 25;
        // H:i 形式に変換: 0時間25分
        $expectedFormattedTime = '0:25';

        // DBの最終確認（念のため）
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('checkin_date', $todayDate)
            ->first();
        $this->assertEquals($expectedTotalBreakMinutes, $attendance->break_total_time, 'DBに記録された総休憩時間が不正です。');

        // 月次勤怠リストページへのアクセスと表示確認
        $response = $this->get(route('user.month.index', [
            'year' => $initialTime->year,
            'month' => $initialTime->month
        ]));

        $response->assertStatus(200);

        // ページビュー内で、計算された「0:25」が表示されていることを確認
        $response->assertSee($expectedFormattedTime,
            "月次勤怠リストに総休憩時間 ({$expectedFormattedTime}) が正しく表示されていません。"
        );

        Carbon::setTestNow();
    }
}