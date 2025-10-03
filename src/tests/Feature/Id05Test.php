<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


// ID05 ステータス表示確認（一般ユーザー）機能のテスト
class Id05Test extends TestCase
{
    use RefreshDatabase;

    /**
     * 各テストで共通して使用するユーザーとテスト時刻を設定します。
     * @return \App\Models\User
     */
    private function setupTestUserAndDate()
    {
        // 1. テスト用の固定時刻を設定（日付を固定することで、その日のレコードとして認識させる）
        $testDate = Carbon::create(2025, 9, 28, 10, 0, 0);
        Carbon::setTestNow($testDate);

        // 2. 認証済みユーザーを作成（メール認証済みを想定）
        // Factoryが存在しない場合は、ここで手動でcreate()を使って作成してください
        $user = User::factory()->create(['email_verified_at' => now()]);

        return $user;
    }

    /**
     * @test
     * 1. 勤務外: ユーザーがその日に一度も出勤していない場合、ステータスが「勤務外」と表示される。
     */
    public function test_displays_status_not_clocked_in(): void
    {
        $user = $this->setupTestUserAndDate();

        // /attendanceページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // 検証: ステータスが「勤務外」であることを確認
        $response->assertStatus(200)
            ->assertSee('<h4 class="status">勤務外</h4>', false);
        
        Carbon::setTestNow(null);
    }

    /**
     * @test
     * 2. 勤務中: 出勤済み、未退勤、かつ休憩中でない場合、ステータスが「勤務中」と表示される。
     */
    public function test_displays_status_working(): void
    {
        $user = $this->setupTestUserAndDate();
        $testTime = Carbon::now();

        // 勤務中状態のレコードを作成（break_timeはnull）
        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $testTime->toDateString(),
            'clock_in_time' => $testTime->copy()->setTime(9, 0, 0),
            'clock_out_time' => null,
            'break_time' => null, // 休憩なし
        ]);

        // /attendanceページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // 検証: ステータスが「勤務中」であることを確認
        $response->assertStatus(200)
            ->assertSee('<h3 class="status">勤務中</h3>', false);
        
        Carbon::setTestNow(null);
    }

    /**
     * @test
     * 3. 休憩中: ユーザーが休憩開始し、break_timeの最後の要素の'end'が空の場合、ステータスが「休憩中」と表示される。
     */
    public function test_displays_status_on_break(): void
    {
        $user = $this->setupTestUserAndDate();
        $testTime = Carbon::now();

        // 休憩中の break_time データを準備 (最後のendがnull/空)
        $breakData = json_encode([
            ['start' => $testTime->copy()->setTime(12, 0, 0)->toDateTimeString(), 'end' => null]
        ]);

        // 休憩中の Attendance レコードを作成
        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $testTime->toDateString(),
            'clock_in_time' => $testTime->copy()->setTime(9, 0, 0),
            'clock_out_time' => null,
            'break_time' => $breakData,
        ]);

        // /attendanceページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // 検証: ステータスが「休憩中」であることを確認
        $response->assertStatus(200)
            ->assertSee('<h4 class="status">休憩中</h4>', false);

        Carbon::setTestNow(null);
    }

    /**
     * @test
     * 4. 退勤済: ユーザーが出勤し、既に退勤（clock_out）している場合、ステータスが「退勤済」と表示される。
     */
    public function test_displays_status_clocked_out(): void
    {
        $user = $this->setupTestUserAndDate();
        $testTime = Carbon::now();

        // 休憩が完了している break_time データを準備
        $breakData = json_encode([
            ['start' => $testTime->copy()->setTime(12, 0, 0)->toDateTimeString(), 'end' => $testTime->copy()->setTime(13, 0, 0)->toDateTimeString()]
        ]);

        // 退勤済み状態のレコードを作成
        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $testTime->toDateString(),
            'clock_in_time' => $testTime->copy()->setTime(9, 0, 0),
            'clock_out_time' => $testTime->copy()->setTime(17, 0, 0), // 退勤済み
            'break_time' => $breakData,
        ]);

        // /attendanceページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // 検証: ステータスが「退勤済」であることを確認
        $response->assertStatus(200)
            ->assertSee('<h4 class="status">退勤済</h4>', false);

        Carbon::setTestNow(null);
    }
}