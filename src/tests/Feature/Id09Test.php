<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

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
        // 後続のナビゲーションテストのためにユーザーを作成
        $this->user = User::factory()->create();
    }
    
    /**
     * テスト後のクリーンアップ（テストで設定した現在時刻の固定を解除）
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }

    /**
     * 認証されたユーザーが、複数データと共に勤怠一覧ページを閲覧できることをテストします。
     *
     * @return void
     */
    public function test_authenticated_user_can_view_attendance_list_with_data()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // テスト対象月（2025年9月）を定義
        $targetDate = Carbon::create(2025, 9, 1);
        $expectedDisplayDate = '2025/09';

        // 1. Attendanceファクトリを使用して、2025/09/01から連続した5日分のデータを一括作成
        $attendances = Attendance::factory()->count(5)->sequence(fn ($sequence) => [
            'user_id' => $user->id,
            'checkin_date' => $targetDate->copy()->addDays($sequence->index)->format('Y-m-d'),
            'clock_in_time' => $targetDate->copy()->addDays($sequence->index)->setTime(9, $sequence->index + 1)->format('H:i:s'),
            'clock_out_time' => $targetDate->copy()->addDays($sequence->index)->setTime(18, $sequence->index + 1)->format('H:i:s'),
            'break_total_time' => 60, // 1時間 (60分) の休憩
            'work_time' => 480,        // 8時間 (480分) の実働
        ])->create();

        // 2. 2025年9月の勤怠一覧ページにアクセス
        $response = $this->actingAs($user)->get('/attendance/list?year=2025&month=9');

        $response->assertStatus(200);

        // 3. 月ナビゲーションのタイトルが存在することをアサート
        $response->assertSee($expectedDisplayDate);
        $response->assertSee('勤怠一覧');

        // 4. 動的にレンダリングされた勤怠データが複数存在し、内容も正しいことをアサート
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
        // 前月: 2025年8月
        $response->assertSee('href="?year=2025&month=8"', false);
        $response->assertSee('前月', false);
        // 翌月: 2025年10月
        $response->assertSee('href="?year=2025&month=10"', false);
        $response->assertSee('翌月', false);
    }


    /**
     * 認証されたユーザーがクエリなしでアクセスしたとき、現在月がデフォルトで表示されることをテストします。
     *
     * @return void
     */
    public function test_authenticated_user_views_current_month_by_default()
    {
        // 1. テスト用の「現在日時」を固定（2025年9月に設定）
        $fixedDate = Carbon::create(2025, 9, 20, 10, 0, 0);
        Carbon::setTestNow($fixedDate);

        $expectedDisplayDate = $fixedDate->format('Y/m');

        // 2. ユーザーを作成し認証する
        $user = User::factory()->create();
        
        // 3. クエリパラメータなしで勤怠一覧ページにアクセス ( /attendance/list )
        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);

        // 4. 月ナビゲーションのタイトルが固定した「現在月」（2025/09）であることを確認
        $response->assertSee($expectedDisplayDate);

        // 5. ナビゲーションリンクのチェック
        // 前月: ?year=2025&month=8
        $response->assertSee('href="?year=2025&month=8"', false);
        $response->assertSee('前月', false);
        // 翌月: ?year=2025&month=10
        $response->assertSee('href="?year=2025&month=10"', false);
        $response->assertSee('翌月', false);
    }
    
    /**
     * 前月へのナビゲーションリンクが正しく機能するかをテストします。
     */
    public function test_can_navigate_to_previous_month(): void
    {
        // 1. テストの基準月を固定します（例: 2024年5月）
        $startYear = 2024;
        $startMonth = 5;

        // 期待される前月 (2024年4月) をCarbonで計算
        $expectedPrevMonth = Carbon::create($startYear, $startMonth)->subMonth();
        // Bladeの出力（ゼロパディングなし）に合わせるため、->format('m')を->monthに変更
        $expectedPrevQuery = "?year={$expectedPrevMonth->year}&month={$expectedPrevMonth->month}";
        $expectedPrevDisplay = $expectedPrevMonth->format('Y/m'); // Bladeで表示される形式 (2024/04)

        // 2. ログインユーザーとして基準月のページにアクセス
        $response = $this->actingAs($this->user)->get("/attendance/list?year={$startYear}&month={$startMonth}");

        $response->assertStatus(200);

        // 3. 基準月（2024/05）が表示されていることと、「前月」リンクのhref属性を確認
        $response->assertSee("{$startYear}/0{$startMonth}");
        // href属性の値のみをチェック
        $response->assertSee('href="' . $expectedPrevQuery . '"', false);

        // 4. 「前月」リンクが示すURLに遷移 (完全なパスを構築)
        $prevMonthResponse = $this->actingAs($this->user)->get("/attendance/list{$expectedPrevQuery}");

        // 5. 前月のページが正しく表示され、期待される月が表示されていることを確認
        $prevMonthResponse->assertStatus(200);
        $prevMonthResponse->assertSee($expectedPrevDisplay);
        $prevMonthResponse->assertDontSee("{$startYear}/0{$startMonth}"); // 基準月が表示されていないことを確認
    }

    /**
     * 翌月へのナビゲーションリンクが正しく機能するかをテストします。
     */
    public function test_can_navigate_to_next_month(): void
    {
        // 1. テストの基準月を固定します（例: 2024年5月）
        $startYear = 2024;
        $startMonth = 5;

        // 期待される翌月 (2024年6月) をCarbonで計算
        $expectedNextMonth = Carbon::create($startYear, $startMonth)->addMonth();
        // Bladeの出力（ゼロパディングなし）に合わせるため、->format('m')を->monthに変更
        $expectedNextQuery = "?year={$expectedNextMonth->year}&month={$expectedNextMonth->month}";
        $expectedNextDisplay = $expectedNextMonth->format('Y/m'); // Bladeで表示される形式 (2024/06)

        // 2. ログインユーザーとして基準月のページにアクセス
        $response = $this->actingAs($this->user)->get("/attendance/list?year={$startYear}&month={$startMonth}");

        $response->assertStatus(200);

        // 3. 基準月（2024/05）が表示されていることと、「翌月」リンクのhref属性を確認
        $response->assertSee("{$startYear}/0{$startMonth}");
        // href属性の値のみをチェック
        $response->assertSee('href="' . $expectedNextQuery . '"', false);

        // 4. 「翌月」リンクが示すURLに遷移 (完全なパスを構築)
        $nextMonthResponse = $this->actingAs($this->user)->get("/attendance/list{$expectedNextQuery}");

        // 5. 翌月のページが正しく表示され、期待される月が表示されていることを確認
        $nextMonthResponse->assertStatus(200);
        $nextMonthResponse->assertSee($expectedNextDisplay);
        $nextMonthResponse->assertDontSee("{$startYear}/0{$startMonth}"); // 基準月が表示されていないことを確認
    }

    /**
     * 詳細ページへ遷移できること、および勤怠情報がフォームの初期値として正しく表示されることをテストします。
     * 出勤・退勤時刻と、記録されている全ての休憩時間がフォームのvalue属性として表示されることを検証します。
     *
     * @return void
     */
    public function test_can_navigate_to_attendance_detail_page_and_see_data(): void
    {
        // ★修正1: 現在日を2025年10月31日に固定し、テスト対象月（2025年10月）の全ての日付が「今日以前」になるようにします。
        $fixedDate = Carbon::create(2025, 10, 31, 10, 0, 0);
        Carbon::setTestNow($fixedDate);

        // 1. ユーザーの勤怠データを作成（テスト対象日: 2025/10/10）
        $targetDate = Carbon::create(2025, 10, 10);
        $unpunchedDate = Carbon::create(2025, 10, 31); // 比較用：レコードのない日（ただし今日）

        // 期待される勤怠データ
        $expectedCheckIn = '09:00';
        $expectedCheckOut = '18:00';
        
        // 2回の休憩データを定義 (データベースにはJSON文字列として保存されることを想定)
        $expectedBreakTimesArray = [
            ['start' => '12:00:00', 'end' => '13:00:00'], // 休憩1 (60分)
            ['start' => '15:00:00', 'end' => '15:15:00'], // 休憩2 (15分)
        ];
        $expectedBreakMinutes = 75; // 休憩合計
        $expectedWorkMinutes = 465; // 実働時間 (540 - 75)

        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate->format('Y-m-d'),
            // データベース保存形式は 'HH:MM:SS'
            'clock_in_time' => "{$expectedCheckIn}:00",
            'clock_out_time' => "{$expectedCheckOut}:00",
            'break_time' => json_encode($expectedBreakTimesArray), // 休憩データをJSON文字列として保存
            'break_total_time' => $expectedBreakMinutes,
            'work_time' => $expectedWorkMinutes,
        ]);

        // 2. 勤怠一覧ページ（2025年10月）にアクセス
        $response = $this->actingAs($this->user)->get('/attendance/list?year=2025&month=10');

        $response->assertStatus(200);

        // 3. 勤怠データが**存在する**日 (2025/10/10) の詳細ボタンをチェック
        // IDを含む形式: /attendance/detail/{id}?date=...
        $expectedPathWithId = "/attendance/detail/{$attendance->id}?date={$targetDate->toDateString()}";
        
        // レンダリングされたHTMLには IDとクラス属性を含み、正確なリンク構造をアサート
        $expectedAnchorWithId = '<a href="http://localhost' . $expectedPathWithId . '" class="detail-button">詳細</a>';
        $response->assertSee($expectedAnchorWithId, false);

        // ★修正2: 勤怠データが**存在しない**日 (2025/10/31) の詳細ボタンをチェック
        // Dateのみの形式: /attendance/detail?date=... (IDはクエリに含めない)
        $expectedPathWithoutId = "/attendance/detail?date={$unpunchedDate->toDateString()}";
        $expectedAnchorWithoutId = '<a href="http://localhost' . $expectedPathWithoutId . '" class="detail-button">詳細</a>';
        $response->assertSee($expectedAnchorWithoutId, false);


        // 4. その詳細URL（相対パス, IDあり）にアクセスし、成功することを確認
        $detailResponse = $this->actingAs($this->user)->get($expectedPathWithId);

        // 詳細ページへのルーティングが存在し、正しく表示されることをアサート
        $detailResponse->assertStatus(200);
        
        // 5. 詳細ページに、作成した勤怠情報がフォームのvalueとして正しく表示されていることを確認
        $detailResponse->assertSee('勤怠詳細・修正申請', 'h2'); // h2タグに変更
        $detailResponse->assertSee($targetDate->format('Y年m月d日')); // 日付の表示を確認

        // ★★★ 出勤・退勤時刻がフォームに正しく初期値としてセットされていることを検証 ★★★
        $detailResponse->assertSee('value="' . $expectedCheckIn . '"', false);      // 出勤時間 (例: value="09:00")
        $detailResponse->assertSee('value="' . $expectedCheckOut . '"', false);     // 退勤時間 (例: value="18:00")
        
        // ★ 休憩1の開始・終了時刻がフォームに表示されていることを検証
        $detailResponse->assertSee('value="12:00"', false); // 休憩1 開始 (H:i形式)
        $detailResponse->assertSee('value="13:00"', false); // 休憩1 終了 (H:i形式)
        
        // ★ 休憩2の開始・終了時刻がフォームに表示されていることを検証
        $detailResponse->assertSee('value="15:00"', false); // 休憩2 開始 (H:i形式)
        $detailResponse->assertSee('value="15:15"', false); // 休憩2 終了 (H:i形式)
    }
}