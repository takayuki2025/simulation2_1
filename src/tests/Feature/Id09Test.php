<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;


// ID09 勤怠一覧情報取得（一般ユーザー）機能のテスト
class Id09Test extends TestCase
{
    use RefreshDatabase;

    /**
     * テストで使用するユーザーをセットアップします。
     * @var User
     */
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // テスト後のクリーンアップ（テストで設定した現在時刻の固定を解除）
    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }

    // ID09-1 自分が行った勤怠情報が全て表示されている（複数データと共に勤怠一覧ページを閲覧できる）ことのテスト。
    public function test_authenticated_user_can_view_attendance_list_with_data()
    {
        $user = User::factory()->create();
        $targetDate = Carbon::create(2025, 9, 1);
        $expectedDisplayDate = '2025/09';

        // Attendanceファクトリを使用して、2025/09/01から連続した5日分のデータを一括作成
        $attendances = Attendance::factory()->count(5)->sequence(fn ($sequence) => [
            'user_id' => $user->id,
            'checkin_date' => $targetDate->copy()->addDays($sequence->index)->format('Y-m-d'),
            'clock_in_time' => $targetDate->copy()->addDays($sequence->index)->setTime(9, $sequence->index + 1)->format('H:i:s'),
            'clock_out_time' => $targetDate->copy()->addDays($sequence->index)->setTime(18, $sequence->index + 1)->format('H:i:s'),
            'break_total_time' => 60, // 1時間 (60分) の休憩
            'work_time' => 480,        // 8時間 (480分) の実働
        ])->create();

        // 2025年9月の勤怠一覧ページにアクセス
        $response = $this->actingAs($user)->get('/attendance/list?year=2025&month=9');
        $response->assertStatus(200);

        // 月ナビゲーションのタイトルが存在することをアサート
        $response->assertSee($expectedDisplayDate); // 2025/09が表示されていること
        $response->assertSee('勤怠一覧');

        // ターゲットではない月が表示されていないことを確認
        $response->assertDontSee('2025/08');
        $response->assertDontSee('2025/10');

        // 動的にレンダリングされた勤怠データが複数存在し、内容も正しいことをアサート
        $daysOfWeek = ['月', '火', '水', '木', '金'];
        $expectedOrder = [];

        for ($i = 0; $i < 5; $i++) {
            $dayMinute = $i + 1; // 1, 2, 3, 4, 5
            $dayOfWeek = $daysOfWeek[$i];

            // 日付表示のチェック
            $expectedOrder[] = "9/0{$dayMinute}({$dayOfWeek})";

            // 出勤時間 (例: 09:01)
            $expectedOrder[] = sprintf('09:%02d', $dayMinute);
            // 退勤時間 (例: 18:01)
            $expectedOrder[] = sprintf('18:%02d', $dayMinute);
            // 休憩時間 (1:00)
            $expectedOrder[] = '1:00';
            // 実働時間 (8:00)
            $expectedOrder[] = '8:00';
            // 詳細ボタン
            $expectedOrder[] = '詳細';
        }

        // 連続するデータ行全体をチェック
        $response->assertSeeInOrder($expectedOrder);

        // ナビゲーションリンクのチェック
        $response->assertSee('href="?year=2025&month=8"', false);
        $response->assertSee('前月', false);
        $response->assertSee('href="?year=2025&month=10"', false);
        $response->assertSee('翌月', false);
    }

    // ID09-2 認証されたユーザーがクエリなしでアクセスしたとき、現在月がデフォルトで表示されることをテストします。
    public function test_authenticated_user_views_current_month_by_default()
    {
        // ログの出力（2025/10）に合わせるため、テスト用の「現在日時」を固定（2025年10月に設定）
        $fixedDate = Carbon::create(2025, 10, 20, 10, 0, 0);
        Carbon::setTestNow($fixedDate);
        $expectedDisplayDate = $fixedDate->format('Y/m'); // 2025/10
        $user = User::factory()->create();

        // クエリパラメータなしで勤怠一覧ページにアクセス
        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);

        $response->assertSee($expectedDisplayDate);
        // ナビゲーションリンクのチェック (前月: 9月, 翌月: 11月)
        $response->assertSee('href="?year=2025&month=9"', false);
        $response->assertSee('前月', false);
        $response->assertSee('href="?year=2025&month=11"', false);
        $response->assertSee('翌月', false);
    }

    // ID09-3 前月へのナビゲーションリンクが正しく機能するかをテストします。
    public function test_can_navigate_to_previous_month(): void
    {
        $startYear = 2024;
        $startMonth = 5;

        // 期待される前月 (2024年4月) をCarbonで計算
        $expectedPrevMonth = Carbon::create($startYear, $startMonth)->subMonth();
        $expectedPrevQuery = "?year={$expectedPrevMonth->year}&month={$expectedPrevMonth->month}";
        $expectedPrevDisplay = $expectedPrevMonth->format('Y/m');

        // ログインユーザーとして基準月のページにアクセス
        $response = $this->actingAs($this->user)->get("/attendance/list?year={$startYear}&month={$startMonth}");
        $response->assertStatus(200);

        // 基準月（2024/05）が表示されていることと、「前月」リンクのhref属性を確認
        $response->assertSee("{$startYear}/0{$startMonth}");
        $response->assertSee('href="' . $expectedPrevQuery . '"', false);
        $response->assertSee('前月', false);

        // 「前月」リンクが示すURLに遷移 (完全なパスを構築)
        $prevMonthResponse = $this->actingAs($this->user)->get("/attendance/list{$expectedPrevQuery}");

        // 前月のページが正しく表示され、期待される月が表示されていることを確認
        $prevMonthResponse->assertStatus(200);
        $prevMonthResponse->assertSee($expectedPrevDisplay);
        $prevMonthResponse->assertDontSee("{$startYear}/0{$startMonth}"); // 基準月が表示されていないことを確認
    }

    // ID09-4 翌月へのナビゲーションリンクが正しく機能するかをテストします。
    public function test_can_navigate_to_next_month(): void
    {
        $startYear = 2024;
        $startMonth = 5;

        // 期待される翌月 (2024年6月) をCarbonで計算
        $expectedNextMonth = Carbon::create($startYear, $startMonth)->addMonth();
        $expectedNextQuery = "?year={$expectedNextMonth->year}&month={$expectedNextMonth->month}";
        $expectedNextDisplay = $expectedNextMonth->format('Y/m');

        // ログインユーザーとして基準月のページにアクセス
        $response = $this->actingAs($this->user)->get("/attendance/list?year={$startYear}&month={$startMonth}");
        $response->assertStatus(200);

        // 基準月（2024/05）が表示されていることと、「翌月」リンクのhref属性を確認
        $response->assertSee("{$startYear}/0{$startMonth}");
        $response->assertSee('href="' . $expectedNextQuery . '"', false);
        $response->assertSee('翌月', false);

        // 「翌月」リンクが示すURLに遷移 (完全なパスを構築)
        $nextMonthResponse = $this->actingAs($this->user)->get("/attendance/list{$expectedNextQuery}");

        // 翌月のページが正しく表示され、期待される月が表示されていることを確認
        $nextMonthResponse->assertStatus(200);
        $nextMonthResponse->assertSee($expectedNextDisplay);
        $nextMonthResponse->assertDontSee("{$startYear}/0{$startMonth}"); // 基準月が表示されていないことを確認
    }

    // ID09-3,4(追加) 特定の月にアクセスしたとき、意図しない他の月の表示が残っていないことをテストします。
    public function test_explicitly_navigating_to_a_month_does_not_show_previous_month()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $targetYear = 2025;
        $targetMonth = 10;
        $targetDisplay = "{$targetYear}/{$targetMonth}"; // 2025/10

        // 以前の月 (2025年9月)が残留していないことを確認
        $previousDisplay = '2025/09';

        $response = $this->get("/attendance/list?year={$targetYear}&month={$targetMonth}");
        $response->assertStatus(200);

        //　正しい月 (2025/10) が表示されていることを確認
        $response->assertSee($targetDisplay);

        // 前の月の文字列 (2025/09) がページに存在しないことを確認
        $response->assertDontSee($previousDisplay);

        // ナビゲーションリンクが正しい月を指していることを確認
        $response->assertSee('href="?year=2025&month=9"', false); // 前月へのリンク
        $response->assertSee('href="?year=2025&month=11"', false); // 翌月へのリンク
        $response->assertSee('前月', false);
        $response->assertSee('翌月', false);
    }

    // ID09-5 詳細ページへ遷移できること、および勤怠情報がフォームの初期値として正しく表示されることをテストします。
    public function test_can_navigate_to_attendance_detail_page_and_see_data(): void
    {
        $fixedDate = Carbon::create(2025, 10, 31, 10, 0, 0);
        Carbon::setTestNow($fixedDate);

        // ユーザーの勤怠データを作成（テスト対象日: 2025/10/10）
        $targetDate = Carbon::create(2025, 10, 10);
        $unpunchedDate = Carbon::create(2025, 10, 31);

        $expectedCheckIn = '09:00';
        $expectedCheckOut = '18:00';

        $expectedBreakTimesArray = [
            ['start' => '12:00:00', 'end' => '13:00:00'], // 休憩1 (60分)
            ['start' => '15:00:00', 'end' => '15:15:00'], // 休憩2 (15分)
        ];
        $expectedBreakMinutes = 75;
        $expectedWorkMinutes = 465;

        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate->format('Y-m-d'),
            'clock_in_time' => "{$expectedCheckIn}:00",
            'clock_out_time' => "{$expectedCheckOut}:00",
            'break_time' => json_encode($expectedBreakTimesArray),
            'break_total_time' => $expectedBreakMinutes,
            'work_time' => $expectedWorkMinutes,
        ]);

        // 勤怠一覧ページ（2025年10月）にアクセス
        $response = $this->actingAs($this->user)->get('/attendance/list?year=2025&month=10');
        $response->assertStatus(200);

        // 勤怠データが**存在する**日 (2025/10/10) の詳細ボタンをチェック
        $expectedPathWithId = route('user.attendance.detail.index', ['id' => $attendance->id, 'date' => $targetDate->toDateString()], false);
        $expectedFullUrlWithId = 'http://localhost' . $expectedPathWithId;

        // レンダリングされたHTMLに、ルート名のURLが含まれていることをアサート
        $expectedAnchorWithId = '<a href="' . $expectedFullUrlWithId . '" class="detail-button">詳細</a>';
        $response->assertSee($expectedAnchorWithId, false);

        // 勤怠データが**存在しない**日 (2025/10/31) の詳細ボタンをチェック,絶対URL (http://localhost...) をレンダリングしているため、期待する文字列を修正します
        $expectedPathWithoutId = route('user.attendance.detail.index', ['date' => $unpunchedDate->toDateString()], false);
        $expectedFullUrlWithoutId = 'http://localhost' . $expectedPathWithoutId;
        $expectedAnchorWithoutId = '<a href="' . $expectedFullUrlWithoutId . '" class="detail-button">詳細</a>';
        $response->assertSee($expectedAnchorWithoutId, false);

        // その詳細URL（IDあり）にアクセスし、成功することを確認,Laravelの route() は相対パスを返すため、そのままgetに渡します。
        $detailResponse = $this->actingAs($this->user)->get($expectedPathWithId);
        $detailResponse->assertStatus(200);

        // 詳細ページに、作成した勤怠情報がフォームのvalueとして正しく表示されていることを確認
        $detailResponse->assertSee('勤怠詳細', 'h2');
        $detailResponse->assertSee($targetDate->format('Y年'));
        $detailResponse->assertSee($targetDate->format('m月d日'));

        // 出勤・退勤時刻、休憩時間がフォームに正しく初期値としてセットされていることを検証
        $detailResponse->assertSee('value="' . $expectedCheckIn . '"', false);
        $detailResponse->assertSee('value="' . $expectedCheckOut . '"', false);

        $detailResponse->assertSee('value="12:00"', false); // 休憩1 開始
        $detailResponse->assertSee('value="13:00"', false); // 休憩1 終了

        $detailResponse->assertSee('value="15:00"', false); // 休憩2 開始
        $detailResponse->assertSee('value="15:15"', false); // 休憩2 終了
    }
}