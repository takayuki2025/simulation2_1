<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;


// ID12 勤怠一覧情報取得（管理者）機能のテスト
class Id12Test extends TestCase
{
    use RefreshDatabase;

    /**
     * テストで使用する管理者ユーザーと一般スタッフユーザー。
     * @var User
     */
    protected $adminUser;
    protected $staffUser;

    protected function setUp(): void
    {
        parent::setUp();
        // 管理者ユーザーを作成 (role: 'admin'を仮定)
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        // 一般スタッフユーザーを作成 (role: 'user'を仮定)
        $this->staffUser = User::factory()->create(['role' => 'user']);
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
     * 【追加テスト】管理者が過去の日付（昨日）を閲覧し、勤怠データが正しく反映されていることをテストします。
     *
     * @return void
     */
    public function test_admin_views_yesterday_with_data()
    {
        // 1. 現在日時を固定 (2025-10-15)
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        
        // ターゲット日（昨日）
        $targetDate = $today->copy()->subDay(); // 2025-10-14
        $dateString = $targetDate->toDateString();

        // 2. スタッフの勤怠データを作成（昨日）
        Attendance::factory()->create([
            'user_id' => $this->staffUser->id,
            'checkin_date' => $dateString,
            'clock_in_time' => '09:30:00',
            'clock_out_time' => '17:30:00',
            'break_total_time' => 90, // 1:30
            'work_time' => 420,        // 7:00
        ]);

        // 3. 管理者として、昨日のページにアクセス
        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));

        $response->assertStatus(200);

        // 4. 表示内容の検証
        $response->assertSee($targetDate->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($this->staffUser->name); 
        $response->assertSee('09:30'); // 出勤時間
        $response->assertSee('17:30'); // 退勤時間
        $response->assertSee('1:30');  // 休憩時間
        $response->assertSee('7:00');  // 合計勤務時間

        // 5. 詳細ボタンの検証（過去の日付なので表示されるはず）
        // ★修正: URLの主要部分と、redirect_toの存在をチェックする
        $response->assertSee('/admin/attendance/' . $this->staffUser->id . '?date=' . $dateString, false);
        $response->assertSee('redirect_to', false);
        $response->assertSee('class="detail-button">詳細</a>', false);
    }
    
    /**
     * 【追加テスト】管理者が今日の日付を閲覧し、勤怠データが正しく反映されていることをテストします。
     *
     * @return void
     */
    public function test_admin_views_today_with_data()
    {
        // 1. 現在日時を固定 (2025-10-15)
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        $dateString = $today->toDateString();

        // 2. スタッフの勤怠データを作成（今日）
        Attendance::factory()->create([
            'user_id' => $this->staffUser->id,
            'checkin_date' => $dateString,
            'clock_in_time' => '09:00:00',
            // 退勤時間は未打刻（null）をシミュレート
            'clock_out_time' => null, 
            'break_total_time' => 0, 
            'work_time' => 60, // 10:00の時点で1時間（60分）経過
        ]);

        // 3. 管理者として、クエリなし（今日）のページにアクセス
        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index'));

        $response->assertStatus(200);

        // 4. 表示内容の検証
        $response->assertSee($today->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($this->staffUser->name); 
        $response->assertSee('09:00'); // 出勤時間
        // 退勤がないため空欄 (<td></td>)
        $response->assertSeeInOrder(['<td>', '</td>', '<td>0:00</td>', '<td>1:00</td>'], false); 

        // 5. 詳細ボタンの検証（今日の日付なので表示されるはず）
        // ★修正: URLの主要部分と、redirect_toの存在をチェックする
        $response->assertSee('/admin/attendance/' . $this->staffUser->id . '?date=' . $dateString, false);
        $response->assertSee('redirect_to', false);
        $response->assertSee('class="detail-button">詳細</a>', false);
    }

    /**
     * 【追加テスト】管理者が未来の日付（明日）を閲覧した場合、勤怠データは空で詳細ボタンは表示されないことをテストします。
     *
     * @return void
     */
    public function test_admin_views_tomorrow_without_data()
    {
        // 1. 現在日時を固定 (2025-10-15)
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        
        // ターゲット日（明日）
        $targetDate = $today->copy()->addDay(); // 2025-10-16
        $dateString = $targetDate->toDateString();

        // 2. スタッフの勤怠データは作成しない（未来の日付なのでデータは存在しない）

        // 3. 管理者として、明日のページにアクセス
        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));

        $response->assertStatus(200);

        // 4. 表示内容の検証
        $response->assertSee($targetDate->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($this->staffUser->name); 
        
        // 勤務情報はすべて空欄であることを確認
        $response->assertSeeInOrder([$this->staffUser->name, '<td></td>', '<td></td>', '<td></td>', '<td></td>'], false);
        
        // 5. 詳細ボタンの検証（未来の日付なので表示されないはず）
        $response->assertDontSee('class="detail-button"');
        
        // 6. 代わりに &nbsp; が表示されていることを検証（未来日付の場合）
        $response->assertSee('&nbsp;', false); 
    }

    // --- 既存のテストメソッド（修正あり） ---

    /**
     * 管理者が過去の日付を閲覧し、勤怠データがない場合に空欄と詳細ボタンが表示されることをテストします。
     *
     * @return void
     */
    public function test_admin_can_view_daily_attendance_for_yesterday_with_no_data()
    {
        // 1. 現在日時を固定 (2025-10-15)
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        
        // ターゲット日（昨日）
        $targetDate = $today->copy()->subDay(); // 2025-10-14
        $dateString = $targetDate->toDateString();

        // 2. スタッフの勤怠データは作成しない（未打刻をシミュレート）

        // 3. 管理者として、前日のページにアクセス
        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));

        $response->assertStatus(200);

        // 4. 表示内容の検証
        $response->assertSee($targetDate->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($this->staffUser->name); // スタッフの名前は表示される
        
        // 勤務情報はすべて空欄であることを確認（<td></td>が順序通りに出現する）
        $response->assertSeeInOrder([$this->staffUser->name, '<td></td>', '<td></td>', '<td></td>', '<td></td>'], false);
        
        // 5. 詳細ボタンの検証（過去の日付なので表示されるはず）
        // ★修正: URLの主要部分と、redirect_toの存在をチェックする
        $response->assertSee('/admin/attendance/' . $this->staffUser->id . '?date=' . $dateString, false);
        $response->assertSee('redirect_to', false);
        $response->assertSee('class="detail-button">詳細</a>', false);
    }
    
    /**
     * 前日へのナビゲーションリンクが正しく機能するかをテストします。
     */
    public function test_admin_can_navigate_to_previous_day()
    {
        // 1. 現在日時を固定 (2025-10-15)
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        $dateString = $today->toDateString();
        
        // 2. 管理者として今日のページにアクセス
        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));
        $response->assertStatus(200);
        
        // 3. 「前日」リンクのhref属性をチェック (2025-10-14)
        $prevDay = $today->copy()->subDay();
        $expectedPrevQuery = '?date=' . $prevDay->toDateString();
        
        // 修正: 相対パス（クエリ部分）のみをチェック
        $response->assertSee('href="' . $expectedPrevQuery . '"', false);
        $response->assertSee('前 日', false);

        // 4. 前日ページに遷移し、日付を確認
        $prevResponse = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $prevDay->toDateString()]));

        $prevResponse->assertStatus(200);
        $prevResponse->assertSee($prevDay->format('Y年m月d日') . 'の勤怠');
        $prevResponse->assertDontSee($today->format('Y年m月d日'));
    }

    /**
     * 翌日へのナビゲーションリンクが正しく機能するかをテストします。
     */
    public function test_admin_can_navigate_to_next_day()
    {
        // 1. 現在日時を固定 (2025-10-15)
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        $dateString = $today->toDateString();
        
        // 2. 管理者として今日のページにアクセス
        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));
        $response->assertStatus(200);
        
        // 3. 「翌日」リンクのhref属性をチェック (2025-10-16)
        $nextDay = $today->copy()->addDay();
        $expectedNextQuery = '?date=' . $nextDay->toDateString();
        
        // 修正: 相対パス（クエリ部分）のみをチェック
        $response->assertSee('href="' . $expectedNextQuery . '"', false);
        $response->assertSee('翌 日', false);

        // 4. 翌日ページに遷移し、日付を確認
        $nextResponse = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $nextDay->toDateString()]));

        $nextResponse->assertStatus(200);
        $nextResponse->assertSee($nextDay->format('Y年m月d日') . 'の勤怠');
        $nextResponse->assertDontSee($today->format('Y年m月d日'));
    }

    /**
     * 管理者が未来の日付を閲覧した場合、詳細ボタンが表示されないことをテストします。
     * (このテストは、test_admin_views_tomorrow_without_dataと目的が重複しますが、既存のテスト名を尊重しアサーションを修正して残します。)
     */
    public function test_admin_cannot_see_detail_button_for_future_date()
    {
        // 1. 現在日時を固定 (2025-10-15)
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        
        // ターゲット日（明日）
        $futureDate = $today->copy()->addDay(); // 2025-10-16
        $dateString = $futureDate->toDateString();

        // 2. 管理者として、未来のページにアクセス
        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));

        $response->assertStatus(200);

        // 3. 表示内容の検証
        $response->assertSee($futureDate->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($this->staffUser->name); 

        // 4. 詳細ボタンのHTML（href属性）が存在しないことをアサート
        $response->assertDontSee('class="detail-button"');
        
        // 5. 代わりに &nbsp; が表示されていることを検証（未来日付の場合）
        $response->assertSee('&nbsp;', false); 
    }
}