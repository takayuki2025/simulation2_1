<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


// ID10 勤怠詳細情報取得（一般ユーザー）機能のテスト
class Id10Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // テスト用のユーザーを作成
        $this->user = User::factory()->create();
    }

    // ID10-1,2,3,4 勤怠詳細ページが、データベースの勤怠データをフォームの初期値として正しく表示することを検証します。
    public function test_attendance_data_is_correctly_loaded_into_form_values(): void
    {
        $targetDate = Carbon::create(2025, 10, 15);

        $expectedCheckIn = '09:00';
        $expectedCheckOut = '18:30';

        $breakTimesArray = [
            ['start' => '12:00:00', 'end' => '13:00:00'], // 休憩1
            ['start' => '16:15:00', 'end' => '16:30:00'], // 休憩2
        ];

        $expectedBreak1Start = '12:00';
        $expectedBreak1End = '13:00';
        $expectedBreak2Start = '16:15';
        $expectedBreak2End = '16:30';

        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate->format('Y-m-d'),
            'clock_in_time' => "{$expectedCheckIn}:00",
            'clock_out_time' => "{$expectedCheckOut}:00",
            'break_time' => json_encode($breakTimesArray),
            'break_total_time' => 75,
            'work_time' => 510,
        ]);

        // 詳細ページURL（IDと日付を含む）にアクセス
        $detailPath = "/attendance/detail/{$attendance->id}?date={$targetDate->toDateString()}";
        $response = $this->actingAs($this->user)->get($detailPath);

        $response->assertStatus(200);

        // ユーザー名と日付の表示を確認
        $response->assertSee($this->user->name);

        $response->assertSee($targetDate->format('Y年'));
        $response->assertSee($targetDate->format('m月d日'));

        // 出勤時刻がフォームのvalueに正しくセットされていることを検証 (09:00)
        // Blade: value="{{ old('clock_in_time', ...format('H:i') : '') }}"
        $response->assertSee('value="' . $expectedCheckIn . '"', false);

        // 退勤時刻がフォームのvalueに正しくセットされていることを検証 (18:30)
        // Blade: value="{{ old('clock_out_time', ...format('H:i') : '') }}"
        $response->assertSee('value="' . $expectedCheckOut . '"', false);

        // 休憩1の開始・終了時刻がフォームのvalueに正しくセットされていることを検証 (12:00, 13:00)
        // Blade: value="{{ old('break_times.0.start_time', $breakTime['start_time'] ?? '') }}"
        $response->assertSee('value="' . $expectedBreak1Start . '"', false);
        $response->assertSee('value="' . $expectedBreak1End . '"', false);

        // 休憩2の開始・終了時刻がフォームのvalueに正しくセットされていることを検証 (16:15, 16:30)
        // Blade: value="{{ old('break_times.1.start_time', $breakTime['start_time'] ?? '') }}"
        $response->assertSee('value="' . $expectedBreak2Start . '"', false);
        $response->assertSee('value="' . $expectedBreak2End . '"', false);
    }
}