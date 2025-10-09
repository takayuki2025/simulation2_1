<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;


// ID04 日時取得（一般ユーザー）機能のテスト
class Id04Test extends TestCase
{
    use RefreshDatabase;

    // ID04(追加) 認証済みの一般ユーザーがアクセスできることを確認します。
    public function test_authenticated_user_can_access_attendance_page(): void
    {
        // 1. 認証済みユーザーを作成（メール認証済みを想定）
        $user = User::factory()->create(['email_verified_at' => now()]);

        // 2. ユーザーとして/attendanceページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // 3. 検証
        $response->assertStatus(200);
    }

    // ID04-1 ページに初期表示される挨拶、日付、時刻が、モックされた時刻と一致していることを確認します。
    public function test_attendance_page_displays_initial_correct_date_and_time(): void
    {
        $testTime = Carbon::create(2025, 9, 28, 10, 30, 0, 'Asia/Tokyo');
        Carbon::setTestNow($testTime);
        $userName = 'テスト ユーザー';
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'name' => $userName,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);

        $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];
        $expectedDate = $testTime->format('Y年m月d日');
        $expectedDay = $dayOfWeekMap[$testTime->dayOfWeek];
        $expectedDateOfWeekText = "{$expectedDate} ({$expectedDay})";

        $response->assertSeeText($expectedDateOfWeekText);

        $expectedTime = $testTime->format('H:i');
        $response->assertSeeText($expectedTime);

        Carbon::setTestNow(null);
    }
}