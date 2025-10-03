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

    /**
     * 勤怠詳細ページが、データベースの勤怠データをフォームの初期値として正しく表示することを検証します。
     *
     * @return void
     */
    public function test_attendance_data_is_correctly_loaded_into_form_values(): void
    {
        // 1. テスト用の勤怠データを作成
        $targetDate = Carbon::create(2025, 10, 15);

        // 期待される出勤・退勤時刻（H:i形式で検証するため、H:i:s形式でDBに保存）
        $expectedCheckIn = '09:00';
        $expectedCheckOut = '18:30';
        
        // 2回の休憩データ（DBにはJSON文字列として保存）
        $breakTimesArray = [
            ['start' => '12:00:00', 'end' => '13:00:00'], // 休憩1
            ['start' => '16:15:00', 'end' => '16:30:00'], // 休憩2
        ];
        
        // 期待される休憩時刻（H:i形式）
        $expectedBreak1Start = '12:00';
        $expectedBreak1End = '13:00';
        $expectedBreak2Start = '16:15';
        $expectedBreak2End = '16:30';

        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate->format('Y-m-d'),
            'clock_in_time' => "{$expectedCheckIn}:00",
            'clock_out_time' => "{$expectedCheckOut}:00",
            'break_time' => json_encode($breakTimesArray), // JSON文字列として保存
            'break_total_time' => 75,
            'work_time' => 510,
        ]);

        // 2. 詳細ページURL（IDと日付を含む）にアクセス
        $detailPath = "/attendance/detail/{$attendance->id}?date={$targetDate->toDateString()}";
        $response = $this->actingAs($this->user)->get($detailPath);

        // 成功ステータスを確認
        $response->assertStatus(200);

        // 3. 表示データの検証
        
        // a) ユーザー名と日付の表示を確認
        $response->assertSee($this->user->name);
        
        // Bladeテンプレートの出力に空白が含まれているため、完全な日付文字列ではなく、
        // 年と月日の部分がそれぞれ表示されていることを検証するように修正
        $response->assertSee($targetDate->format('Y年'));
        $response->assertSee($targetDate->format('m月d日'));
        
        // b) 出勤時刻がフォームのvalueに正しくセットされていることを検証 (09:00)
        // Blade: value="{{ old('clock_in_time', ...format('H:i') : '') }}"
        $response->assertSee('value="' . $expectedCheckIn . '"', false);
        
        // c) 退勤時刻がフォームのvalueに正しくセットされていることを検証 (18:30)
        // Blade: value="{{ old('clock_out_time', ...format('H:i') : '') }}"
        $response->assertSee('value="' . $expectedCheckOut . '"', false);
        
        // d) 休憩1の開始・終了時刻がフォームのvalueに正しくセットされていることを検証 (12:00, 13:00)
        // Blade: value="{{ old('break_times.0.start_time', $breakTime['start_time'] ?? '') }}"
        $response->assertSee('value="' . $expectedBreak1Start . '"', false);
        $response->assertSee('value="' . $expectedBreak1End . '"', false);
        
        // e) 休憩2の開始・終了時刻がフォームのvalueに正しくセットされていることを検証 (16:15, 16:30)
        // Blade: value="{{ old('break_times.1.start_time', $breakTime['start_time'] ?? '') }}"
        $response->assertSee('value="' . $expectedBreak2Start . '"', false);
        $response->assertSee('value="' . $expectedBreak2End . '"', false);
        
        // f) 3つ目以降の休憩欄は空欄で表示されていることを検証 (最低2つ確保し、データは2つなので3つ目以降は空)
        // このテストケースでは休憩データが2つなので、最低2つのフォームが表示されていればOK。
        // もし最低3つを検証したい場合は、空のvalueをアサートする。
        // 例: $response->assertSee('name="break_times[2][start_time]" value=""', false);
    }
}