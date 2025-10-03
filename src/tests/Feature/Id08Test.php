<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;


// ID08 退勤（一般ユーザー）機能のテスト
class Id08Test extends TestCase
{
    use RefreshDatabase;

    /**
     * 出勤中のユーザーが退勤処理を行い、ステータスが「退勤済」になることをテストします。
     *
     * @return void
     */
    public function test_user_can_clock_out_and_status_changes_to_completed()
    {
        // === 1. 時間固定とユーザーのセットアップ ===
        $fixedClockInTime = Carbon::create(2025, 10, 1, 9, 0, 0, 'Asia/Tokyo');
        $fixedClockOutTime = $fixedClockInTime->copy()->addHours(8); // 17:00:00

        // ユーザー作成とログイン
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);

        // 勤怠レコードの作成（出勤済み状態を直接作成）
        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $fixedClockInTime->toDateString(), 
            'clock_in_time' => $fixedClockInTime->toDateTimeString(), 
            'clock_out_time' => null, 
            'break_time' => '[]', 
            'break_total_time' => 0,
        ]);
        
        // --- 勤務中状態の初期確認 (UI: 退勤ボタンがあるか) ---
        Carbon::setTestNow($fixedClockInTime);
        $this->get(route('user.stamping.index'))
             ->assertStatus(200)
             ->assertSee('勤務中')
             ->assertSee('退勤'); 

        // === 2. 退勤処理の実行 (attendance_create関数を実行) ===
        Carbon::setTestNow($fixedClockOutTime);
        
        // attendance.createは退勤ルートと仮定
        $this->post(route('attendance.create')) 
             ->assertRedirect(route('user.stamping.index'));

        // === 3. 最終状態の確認 ===

        // A. UI確認: 退勤処理後の画面表示
        $response = $this->get(route('user.stamping.index'));
        $response->assertStatus(200);
        $response->assertSee('退勤済'); 
        $response->assertDontSee('休憩入'); 

        // B. DB確認: clock_out_timeが記録されていること
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('checkin_date', $fixedClockInTime->toDateString())
            ->first();

        $this->assertNotNull($attendance, '退勤処理後に勤怠レコードが見つかりません。');
        $this->assertNotNull($attendance->clock_out_time, '退勤処理後、clock_out_timeがNULLであってはなりません。');
        
        $this->assertEquals(
            $fixedClockOutTime->toDateTimeString(), 
            $attendance->clock_out_time, 
            '記録された退勤時刻が固定時間と一致しません。'
        );

        Carbon::setTestNow(); 
    }


    /**
     * 退勤処理を完了した日の勤怠データが、月別勤怠一覧ページに正しく表示されることをテストします。
     *
     * @return void
     */
    public function test_completed_attendance_is_displayed_on_monthly_list()
    {
        // === 1. セットアップと時間固定 ===
        
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($user);
        
        // 勤怠処理を行う日と時刻を固定 (2025年11月15日)
        $targetDate = Carbon::create(2025, 11, 15, 0, 0, 0, 'Asia/Tokyo');
        
        // Carbonのロケールを日本語に設定（曜日表示を合わせるため）
        $originalLocale = Carbon::getLocale();
        Carbon::setLocale('ja'); // 日本語ロケールを設定

        // 予定される出勤・退勤時刻
        $clockInTime = $targetDate->copy()->setTime(9, 30, 0);  // 11月15日 09:30:00
        $clockOutTime = $targetDate->copy()->setTime(18, 15, 0); // 11月15日 18:15:00
        
        // ページに表示される日付の期待値 (例: 11/15(土)) - Bladeテンプレートの出力形式に合わせて 'm/d(D)' 形式を使用
        // Carbon::format()は環境によってはロケールを無視して英語略称（Sat）を返すことがあるため、
        // translatedFormat()を使用してCarbonの内部翻訳を利用するように修正します。
        $expectedDayLabel = $targetDate->translatedFormat('m/d(D)'); // これで確実に '11/15(土)' になります。
        $expectedMonthDisplay = $targetDate->format('Y/m'); // 例: '2025/11'
        
        // === 2. 勤怠処理の実行 ===
        
        // 出勤処理
        Carbon::setTestNow($clockInTime);
        $this->post(route('attendance.clock_in')); 
        
        // 退勤処理
        Carbon::setTestNow($clockOutTime);
        $this->post(route('attendance.create')); 

        // DBのデータが更新されたことを確認
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('checkin_date', $targetDate->toDateString())
            ->first();
        $this->assertNotNull($attendance->clock_out_time, '退勤時刻が記録されていません。');
        
        // === 3. 月別勤怠一覧ページへのアクセスと表示検証 ===
        
        // ★修正点1: URLクエリパラメータを渡し、2025年11月のデータを明示的に要求する。
        $response = $this->get(route('user.month.index', [
            'year' => $targetDate->year, 
            'month' => $targetDate->month
        ])); 

        $response->assertStatus(200);
        
        // Bladeファイルで表示される時刻のフォーマット (H:i) に合わせる
        $expectedClockIn = $clockInTime->format('H:i');   
        $expectedClockOut = $clockOutTime->format('H:i'); 
        
        // ★修正点2: 月表示ヘッダーが正しく2025/11になっていることを確認
        $response->assertSee($expectedMonthDisplay);

        // 期待する日付ラベル（m/d(D)形式）と時刻が含まれていることを確認
        $response->assertSee($expectedDayLabel); 
        $response->assertSee($expectedClockIn);
        $response->assertSee($expectedClockOut);
        
        // Carbonのロケールを元に戻す
        Carbon::setLocale($originalLocale);
        Carbon::setTestNow(); 
    }
}