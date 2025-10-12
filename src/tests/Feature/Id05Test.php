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

    private function setupTestUserAndDate()
    {
        $testDate = Carbon::create(2025, 9, 28, 10, 0, 0);
        Carbon::setTestNow($testDate);

        $user = User::factory()->create(['email_verified_at' => now()]);

        return $user;
    }

    // ID05-1 勤務外: ユーザーがその日に一度も出勤していない場合、ステータスが「勤務外」と表示される。
    public function test_displays_status_not_clocked_in(): void
    {
        $user = $this->setupTestUserAndDate();

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200)
            ->assertSee('<h4 class="status">勤務外</h4>', false);

        Carbon::setTestNow(null);
    }

    // ID05-2 出勤中: 出勤済み、未退勤、かつ休憩中でない場合、ステータスが「出勤中」と表示される。
    public function test_displays_status_working(): void
    {
        $user = $this->setupTestUserAndDate();
        $testTime = Carbon::now();

        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $testTime->toDateString(),
            'clock_in_time' => $testTime->copy()->setTime(9, 0, 0),
            'clock_out_time' => null,
            'break_time' => null, // 休憩なし
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200)
            ->assertSee('<h4 class="status">出勤中</h4>', false);

        Carbon::setTestNow(null);
    }

    // ID05-3 休憩中: ユーザーが休憩開始し、break_timeの最後の要素の'end'が空の場合、ステータスが「休憩中」と表示される。
    public function test_displays_status_on_break(): void
    {
        $user = $this->setupTestUserAndDate();
        $testTime = Carbon::now();

        $breakData = json_encode([
            ['start' => $testTime->copy()->setTime(12, 0, 0)->toDateTimeString(), 'end' => null]
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $testTime->toDateString(),
            'clock_in_time' => $testTime->copy()->setTime(9, 0, 0),
            'clock_out_time' => null,
            'break_time' => $breakData,
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200)
            ->assertSee('<h4 class="status">休憩中</h4>', false);

        Carbon::setTestNow(null);
    }

    // ID05-4 退勤済: ユーザーが出勤し、既に退勤（clock_out）している場合、ステータスが「退勤済」と表示される。
    public function test_displays_status_clocked_out(): void
    {
        $user = $this->setupTestUserAndDate();
        $testTime = Carbon::now();

        $breakData = json_encode([
            ['start' => $testTime->copy()->setTime(12, 0, 0)->toDateTimeString(), 'end' => $testTime->copy()->setTime(13, 0, 0)->toDateTimeString()]
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'checkin_date' => $testTime->toDateString(),
            'clock_in_time' => $testTime->copy()->setTime(9, 0, 0),
            'clock_out_time' => $testTime->copy()->setTime(17, 0, 0),
            'break_time' => $breakData,
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200)
            ->assertSee('<h4 class="status">退勤済</h4>', false);

        Carbon::setTestNow(null);
    }
}