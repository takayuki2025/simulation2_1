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

    /**
     * [前回のテスト] 休憩開始し、UIが正しく「休憩中」に更新されることをテストします。
     *
     * @return void
     */
    public function test_user_can_start_break_and_ui_updates()
    {
        // === 1. 時間固定のセットアップ ===
        $fixedNow = Carbon::create(2025, 9, 29, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixedNow);

        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);

        // 勤怠レコードの作成（出勤済み状態）
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

        // --- 勤務中状態の初期確認 ---
        $this->get(route('user.stamping.index'))
             ->assertStatus(200)
             ->assertSee('勤務中') 
             ->assertSee('休憩入')
             ->assertDontSee('休憩戻'); 

        // 休憩開始アクションの実行
        $this->post(route('attendance.break_start'))
             ->assertRedirect(route('user.stamping.index'));

        // --- 休憩中状態への切り替え確認 ---
        $this->get(route('user.stamping.index'))
             ->assertStatus(200)
             ->assertSee('休憩中')
             ->assertSee('休憩戻')
             ->assertDontSee('休憩入'); 
        
        Carbon::setTestNow();
    }

    /**
     * [前回のテスト] 休憩開始→休憩終了のサイクル全体と、それに伴うUIの更新をテストします。
     *
     * @return void
     */
    public function test_user_can_start_and_end_break_cycle()
    {
        // 時間固定は各ステップで調整するため、ここでは初期設定のみ
        $initialTime = Carbon::create(2025, 9, 29, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($initialTime);

        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);

        // 勤務開始時刻 (09:00:00)
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

        // 1. --- 休憩開始 (10:00:00) ---
        $this->post(route('attendance.break_start'));
        // UI: 休憩戻
        $this->get(route('user.stamping.index'))->assertSee('休憩戻');
        
        // 2. 休憩時間をシミュレーション (10:30:00 に進める)
        $breakEndTime = $initialTime->copy()->addMinutes(30);
        Carbon::setTestNow($breakEndTime);

        // 3. --- 休憩終了 (10:30:00) ---
        $this->post(route('attendance.break_end'));

        // 4. --- 最終状態 (勤務中) の確認 (UI: 休憩入) ---
        $this->get(route('user.stamping.index'))
             ->assertStatus(200)
             ->assertSee('勤務中')
             ->assertSee('休憩入')
             ->assertDontSee('休憩戻');

        // データベースの状態確認
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('checkin_date', $todayDate)
            ->first();
        $breakTimes = $attendance->break_time;
        $this->assertCount(1, $breakTimes);
        $this->assertEquals($breakEndTime->toDateTimeString(), $breakTimes[0]['end']);

        Carbon::setTestNow();
    }
    
    /**
     * 休憩の開始と終了をループで複数回繰り返し、UIとDBの状態が常に正しいことを動的にテストします。
     *
     * @return void
     */
    public function test_dynamic_break_cycles_verify_button_toggling()
    {
        // === 1. 初期設定 ===
        $cycles = 5; // 繰り返す休憩サイクルの回数
        $breakDurationMinutes = 10; // 休憩時間
        $workDurationMinutes = 10; // 休憩間の勤務時間

        // 初期時刻とユーザー設定
        $initialTime = Carbon::create(2025, 9, 29, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($initialTime); // 09:00:00
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);
        $todayDate = $initialTime->toDateString(); 

        // 勤怠レコードの作成（出勤済み状態）
        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $todayDate, 
            'clock_in_time' => $initialTime->toDateTimeString(), // 09:00:00 出勤
            'checkout_time' => null, 
            'break_time' => '[]', 
            'break_total_time' => 0,
        ]);
        
        // 最初の休憩サイクル開始時刻 (10:00:00からスタート)
        $currentTime = $initialTime->copy()->addHour(); // 10:00:00

        // ----------------------------------------------------
        // 2. --- N回 (5回) の休憩サイクルをループで実行 ---
        // ----------------------------------------------------
        for ($i = 0; $i < $cycles; $i++) {
            
            // 現在時刻を休憩開始時刻にセット
            Carbon::setTestNow($currentTime);
            $breakStartTime = $currentTime->copy();

            // A. 休憩開始アクションの実行
            $this->post(route('attendance.break_start'))
                 ->assertRedirect(route('user.stamping.index'));
            
            // UI確認: 休憩中 (休憩戻ボタンが表示されていること)
            $this->get(route('user.stamping.index'))
                 ->assertSee('休憩中')
                 ->assertSee('休憩戻')
                 ->assertDontSee('休憩入', '休憩中は休憩入ボタンが表示されないことを保証します。');
            
            // 休憩時間を進める
            $breakEndTime = $currentTime->copy()->addMinutes($breakDurationMinutes);
            Carbon::setTestNow($breakEndTime);

            // B. 休憩終了アクションの実行
            $this->post(route('attendance.break_end'))
                 ->assertRedirect(route('user.stamping.index'));

            // UI確認: 勤務中 (休憩入ボタンが表示されていること)
            $this->get(route('user.stamping.index'))
                 ->assertSee('勤務中')
                 ->assertSee('休憩入')
                 ->assertDontSee('休憩戻', '勤務中は休憩戻ボタンが表示されないことを保証します。');

            // 次のサイクルの開始時刻を計算（勤務時間を進める）
            $currentTime = $breakEndTime->copy()->addMinutes($workDurationMinutes);
            
            // ------------------------------------------------
            // データベース検証 (毎回、最後の休憩レコードが終了していることを確認)
            // ------------------------------------------------
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
    
    /**
     * ステータスが勤務中のユーザーが、複数回の休憩後、
     * 月次勤怠リスト（user_month_index）ページで総休憩時間が正しく表示されることをテストします。
     *
     * @return void
     */
    public function test_total_break_time_is_displayed_on_monthly_list()
    {
        // === 1. 初期設定と勤務開始時刻の準備 ===
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);
        
        $initialTime = Carbon::create(2025, 9, 29, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($initialTime); // 09:00:00 出勤
        $todayDate = $initialTime->toDateString(); 

        // 勤怠レコードの作成（出勤済み状態）
        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $todayDate, 
            'clock_in_time' => $initialTime->toDateTimeString(),
            'checkout_time' => null, 
            'break_time' => '[]', 
            'break_total_time' => 0,
        ]);
        
        // ----------------------------------------------------
        // 2. --- 複数回休憩サイクルを実行し、合計25分の休憩を取得 ---
        // ----------------------------------------------------

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
        
        // ----------------------------------------------------
        // 3. --- 月次勤怠リストページへのアクセスと表示確認 ---
        // ----------------------------------------------------
        
        // 現在の年月のリストページにアクセス
        $response = $this->get(route('user.month.index', [
            'year' => $initialTime->year, 
            'month' => $initialTime->month
        ]));

        $response->assertStatus(200);
        
        // ページビュー内で、計算された「0:25」が表示されていることを確認
        // この値は、勤怠データテーブルの「休憩時間」列に表示されることを想定
        $response->assertSee($expectedFormattedTime, 
            "月次勤怠リストに総休憩時間 ({$expectedFormattedTime}) が正しく表示されていません。"
        );

        Carbon::setTestNow();
    }
}