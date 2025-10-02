<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * ID04: ユーザーの勤怠打刻ページ（/attendance）に関するテストスイート
 *
 * 【修正方針】
 * 期待値はCarbon::setTestNow()で固定した時刻から動的に計算するようにし、
 * コントローラーがCarbonを正しく利用していれば、常に安定してパスするようにします。
 */
class Id04Test extends TestCase
{
    // テスト後にデータベースをリセット
    use RefreshDatabase;

    /**
     * 認証済みの一般ユーザーがアクセスできることを確認します。
     */
    public function test_authenticated_user_can_access_attendance_page(): void
    {
        // 1. 認証済みユーザーを作成（メール認証済みを想定）
        $user = User::factory()->create(['email_verified_at' => now()]);

        // 2. ユーザーとして/attendanceページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // 3. 検証
        $response->assertStatus(200);
    }

    /**
     * ページに初期表示される挨拶、日付、時刻が、モックされた時刻と一致していることを確認します。
     */
    public function test_attendance_page_displays_initial_correct_date_and_time(): void
    {
        // 1. テスト用の固定時刻を設定 (例: 2025年9月28日 10:30:00 日曜日)
        $testTime = Carbon::create(2025, 9, 28, 10, 30, 0, 'Asia/Tokyo');
        Carbon::setTestNow($testTime);
        // 以降、Carbon::now() は $testTime の値を返します。

        // 2. 認証済みユーザーを作成（挨拶文検証用に名前を固定）
        $userName = 'テスト ユーザー';
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'name' => $userName,
        ]);

        // 3. ユーザーとして/attendanceページにアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // 4. ステータスコードの検証
        $response->assertStatus(200);

        // 5. HTMLコンテンツ内の初期値が含まれていることを、モックされた時刻から動的に計算して検証

        // 5-1. 挨拶文の検証 (設定時刻10:30に基づき「おはようございます」を期待)
        // コントローラーのロジックを再現: 6時〜12時未満なら「おはようございます」
        $expectedGreeting = 'こんばんは、'; // デフォルト値
        if ($testTime->hour >= 6 && $testTime->hour < 12) {
            $expectedGreeting = 'おはようございます、';
        } elseif ($testTime->hour >= 12 && $testTime->hour < 18) {
            $expectedGreeting = 'こんにちは、';
        }
        $response->assertSeeText("{$expectedGreeting}{$userName}さん");

        // 5-2. 日付と曜日の検証 (ControllerがCarbonを使用している前提で動的に生成)
        // コントローラーで使用されている日付フォーマットと曜日マップを再現
        $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];
        $expectedDate = $testTime->format('Y年m月d日');
        $expectedDay = $dayOfWeekMap[$testTime->dayOfWeek];
        $expectedDateOfWeekText = "{$expectedDate} ({$expectedDay})";
        
        $response->assertSeeText($expectedDateOfWeekText);
        
        // 5-3. 時刻の検証 (ControllerがCarbonを使用している前提で動的に生成)
        $expectedTime = $testTime->format('H:i');
        $response->assertSeeText($expectedTime); 

        // 6. テスト終了後、固定時刻設定を解除
        Carbon::setTestNow(null);
    }
}