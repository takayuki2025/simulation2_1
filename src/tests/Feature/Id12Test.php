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
    protected $staffUser1;
    protected $staffUser2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->staffUser1 = User::factory()->create(['role' => 'employee']);
        $this->staffUser2 = User::factory()->create(['role' => 'employee']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }

    // ID12-1 管理者が過去の日付（昨日）を閲覧し、勤怠データが正しく反映されていることおよび複数のスタッフの情報が正確に表示されていることをテストします。
    public function test_admin_views_yesterday_with_data()
    {
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);

        // ターゲット日（昨日）
        $targetDate = $today->copy()->subDay(); // 2025-10-14
        $dateString = $targetDate->toDateString();

        // スタッフ1の勤怠データを作成（昨日）
        Attendance::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $dateString,
            'clock_in_time' => '09:30:00',
            'clock_out_time' => '17:30:00',
            'break_total_time' => 90, // 1:30
            'work_time' => 420,        // 7:00
        ]);

        // スタッフ2の勤怠データを作成（昨日）
        Attendance::factory()->create([
            'user_id' => $this->staffUser2->id,
            'checkin_date' => $dateString,
            'clock_in_time' => '08:00:00',
            'clock_out_time' => '18:00:00',
            'break_total_time' => 60, // 1:00
            'work_time' => 540,        // 9:00
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));

        $response->assertStatus(200);

        // 表示内容の検証
        $response->assertSee($targetDate->format('Y年m月d日') . 'の勤怠');

        // スタッフ1の情報を検証
        $response->assertSee($this->staffUser1->name);
        $response->assertSee('09:30'); // 出勤時間
        $response->assertSee('17:30'); // 退勤時間
        $response->assertSee('1:30');  // 休憩時間
        $response->assertSee('7:00');  // 合計勤務時間
        $response->assertSee('/admin/attendance/' . $this->staffUser1->id . '?date=' . $dateString, false);

        // スタッフ2の情報を検証
        $response->assertSee($this->staffUser2->name);
        $response->assertSee('08:00'); // 出勤時間
        $response->assertSee('18:00'); // 退勤時間
        $response->assertSee('1:00');  // 休憩時間
        $response->assertSee('9:00');  // 合計勤務時間
        $response->assertSee('/admin/attendance/' . $this->staffUser2->id . '?date=' . $dateString, false);

        // 詳細ボタンの検証（過去の日付なので表示される）
        $response->assertSee('redirect_to', false);
        $response->assertSee('class="detail-button">詳細</a>', false);
    }

    // ID12-2 管理者が今日の日付を閲覧し、勤怠データが正しく反映されていることおよび複数のスタッフの情報が正確に表示されていることをテストします。また、日付クエリがない場合に現在の日付が表示されていることを検証します。
    public function test_admin_views_today_with_data_and_multiple_staff()
    {
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        $dateString = $today->toDateString();

        // スタッフ1の勤怠データを作成（今日: 途中まで勤務）
        Attendance::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $dateString,
            'clock_in_time' => '09:00:00',
            // 退勤時間は未打刻（null）をシミュレート
            'clock_out_time' => null,
            'break_total_time' => 0,
            'work_time' => 60, // 10:00の時点で1時間（60分）経過
        ]);

        // スタッフ2の勤怠データを作成（今日: 途中まで勤務、休憩あり）
        Attendance::factory()->create([
            'user_id' => $this->staffUser2->id,
            'checkin_date' => $dateString,
            'clock_in_time' => '08:30:00',
            'clock_out_time' => null, // 未打刻
            'break_total_time' => 30, // 30分休憩
            // 勤務時間：(10:00 - 08:30) - 0:30休憩 = 1:00 (60分)
            'work_time' => 60,
        ]);

        // 管理者として、クエリなし（今日）のページにアクセス
        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index'));

        $response->assertStatus(200);

        // 表示されている日付が現在の日付であることを検証
        $response->assertSee($today->format('Y年m月d日') . 'の勤怠');

        // スタッフ1の情報が正確に表示されていることを検証
        $response->assertSee($this->staffUser1->name);
        $response->assertSeeInOrder([$this->staffUser1->name, '<td>09:00</td>', '<td></td>', '<td>0:00</td>', '<td>1:00</td>'], false);

        // スタッフ2の情報が正確に表示されていることを検証
        $response->assertSee($this->staffUser2->name);
        $response->assertSeeInOrder([$this->staffUser2->name, '<td>08:30</td>', '<td></td>', '<td>0:30</td>', '<td>1:00</td>'], false);


        // 詳細ボタンの検証（今日の日付なので表示される）
        $response->assertSee('/admin/attendance/' . $this->staffUser1->id . '?date=' . $dateString, false);
        $response->assertSee('redirect_to', false);
        $response->assertSee('class="detail-button">詳細</a>', false);
    }

    // ID12-3 前日へのナビゲーションリンクが正しく機能するかをテストします。
    public function test_admin_can_navigate_to_previous_day()
    {
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        $dateString = $today->toDateString();

        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));
        $response->assertStatus(200);

        // 「前日」リンクのhref属性をチェック (2025-10-14)
        $prevDay = $today->copy()->subDay();
        $expectedPrevQuery = '?date=' . $prevDay->toDateString();

        // 相対パス（クエリ部分）のみをチェック
        $response->assertSee('href="' . $expectedPrevQuery . '"', false);
        $response->assertSee('前 日', false);

        // 前日ページに遷移し、日付を確認
        $prevResponse = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $prevDay->toDateString()]));

        $prevResponse->assertStatus(200);
        $prevResponse->assertSee($prevDay->format('Y年m月d日') . 'の勤怠');
        $prevResponse->assertDontSee($today->format('Y年m月d日'));
    }

    // ID12-3(追加) 管理者が過去の日付を閲覧し、勤怠データがない場合に空欄と詳細ボタンが表示されることをテストします。
    public function test_admin_can_view_daily_attendance_for_yesterday_with_no_data()
    {
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);

        // ターゲット日（昨日）
        $targetDate = $today->copy()->subDay(); // 2025-10-14
        $dateString = $targetDate->toDateString();

        // 2. スタッフの勤怠データは作成しない（未打刻をシミュレート）

        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));

        $response->assertStatus(200);

        // 表示内容の検証
        $response->assertSee($targetDate->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($this->staffUser1->name); // スタッフ1の名前は表示される
        $response->assertSee($this->staffUser2->name); // スタッフ2の名前は表示される

        $response->assertSeeInOrder([$this->staffUser1->name, '<td></td>', '<td></td>', '<td></td>', '<td></td>'], false);
        $response->assertSeeInOrder([$this->staffUser2->name, '<td></td>', '<td></td>', '<td></td>', '<td></td>'], false);

        // 詳細ボタンの検証（過去の日付なので表示されるはず）
        $response->assertSee('/admin/attendance/' . $this->staffUser1->id . '?date=' . $dateString, false);
        $response->assertSee('/admin/attendance/' . $this->staffUser2->id . '?date=' . $dateString, false);
        $response->assertSee('redirect_to', false);
        $response->assertSee('class="detail-button">詳細</a>', false);
    }

    // ID12-4 翌日へのナビゲーションリンクが正しく機能するかをテストします。
    public function test_admin_can_navigate_to_next_day()
    {
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);
        $dateString = $today->toDateString();

        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));
        $response->assertStatus(200);

        // 「翌日」リンクのhref属性をチェック (2025-10-16)
        $nextDay = $today->copy()->addDay();
        $expectedNextQuery = '?date=' . $nextDay->toDateString();

        // 相対パス（クエリ部分）のみをチェック
        $response->assertSee('href="' . $expectedNextQuery . '"', false);
        $response->assertSee('翌 日', false);

        // 翌日ページに遷移し、日付を確認
        $nextResponse = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $nextDay->toDateString()]));

        $nextResponse->assertStatus(200);
        $nextResponse->assertSee($nextDay->format('Y年m月d日') . 'の勤怠');
        $nextResponse->assertDontSee($today->format('Y年m月d日'));
    }

    // ID12-4(追加) 管理者が未来の日付（明日）を閲覧した場合、勤怠データは空で詳細ボタンは表示されないことをテストします。
    public function test_admin_views_tomorrow_without_data()
    {
        $today = Carbon::create(2025, 10, 15, 10, 0, 0);
        Carbon::setTestNow($today);

        // ターゲット日（明日）
        $targetDate = $today->copy()->addDay(); // 2025-10-16
        $dateString = $targetDate->toDateString();

        // スタッフの勤怠データは作成しない（未来の日付なのでデータは存在しない）

        $response = $this->actingAs($this->adminUser)->get(route('admin.attendance.list.index', ['date' => $dateString]));

        $response->assertStatus(200);

        $response->assertSee($targetDate->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($this->staffUser1->name);
        $response->assertSee($this->staffUser2->name);

        $response->assertSeeInOrder([$this->staffUser1->name, '<td></td>', '<td></td>', '<td></td>', '<td></td>'], false);
        $response->assertSeeInOrder([$this->staffUser2->name, '<td></td>', '<td></td>', '<td></td>', '<td></td>'], false);

        // 詳細ボタンの検証（未来の日付なので表示されない）
        $response->assertDontSee('class="detail-button"');

        // 代わりに &nbsp; が表示されていることを検証（未来日付の場合）
        $response->assertSee('&nbsp;', false);
    }
}