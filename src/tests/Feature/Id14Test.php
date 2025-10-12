<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;


// ID14 ユーザー情報取得（管理者）機能のテスト
class Id14Test extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $staffUser1;
    protected $staffUser2;
    protected $testDatePast;
    protected $testDatePreviousMonth;
    protected $attendanceA;
    protected $attendanceA_prev_month;
    protected $applicationA;

    protected function setUp(): void
    {
        parent::setUp();

        // 管理者ユーザー
        $this->adminUser = User::factory()->create(['role' => 'admin', 'name' => '管理者X', 'id' => 100]);
        // スタッフユーザー (role: employee)
        $this->staffUser1 = User::factory()->create(['role' => 'employee', 'name' => 'テストスタッフA', 'email' => 'test_a@example.com', 'id' => 2]);
        $this->staffUser2 = User::factory()->create(['role' => 'employee', 'name' => 'テストスタッフB', 'email' => 'test_b@example.com', 'id' => 3]);

        // 勤怠データ（テスト対象の基準日: 2025-09-25）
        $this->testDatePast = '2025-09-25';
        // 前月の日付: 2025-08-20
        $this->testDatePreviousMonth = '2025-08-20';

        // 勤怠レコード（スタッフA, 9月25日 - 基準となるデータ）
        $this->attendanceA = Attendance::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $this->testDatePast,
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'break_total_time' => 60,
            'work_time' => 480,
        ]);

        // 前月の勤怠レコード（スタッフA, 8月20日）
        $this->attendanceA_prev_month = Attendance::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $this->testDatePreviousMonth,
            'clock_in_time' => '08:30:00', // 9月とは異なる時刻
            'clock_out_time' => '17:30:00',
            'break_total_time' => 60,
            'work_time' => 480,
        ]);

        // 申請レコード（スタッフA - 勤怠データを上書きする内容, 9月25日）
        $this->applicationA = Application::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $this->testDatePast,
            'clock_in_time' => '09:15:00',
            'clock_out_time' => '18:15:00',
            'reason' => '申請による修正',
        ]);
    }

    // ID14-1 管理者スタッフ一覧ページ (admin.staff.list.index) の表示を検証する。
    public function test_admin_staff_list_index_displays_all_staff()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.staff.list.index'));
        $response->assertStatus(200);
        $response->assertSee('スタッフ一覧');

        // スタッフAの情報が表示されていること
        $response->assertSee($this->staffUser1->name);
        $response->assertSee($this->staffUser1->email);
        // スタッフBの情報が表示されていること
        $response->assertSee($this->staffUser2->name);
        $response->assertSee($this->staffUser2->email);
        // スタッフAの月次勤怠へのリンクが存在すること
        $response->assertSee(route('admin.staff.month.index', ['id' => $this->staffUser1->id]));
        $response->assertSee('詳細');
        // ログイン中の管理者自身の情報は一覧に含まれないことを確認
        $response->assertDontSee($this->adminUser->name);
    }

    // ID14-2 スタッフ月次勤怠ページ (admin.staff.month.index) の表示を検証する。
    public function test_admin_staff_month_index_displays_correct_data_and_links()
    {
        $targetDate = Carbon::parse($this->testDatePast);
        $year = $targetDate->year;
        $month = $targetDate->month;
        $expectedMonthDisplay = $targetDate->format('Y/m'); // 2025/09

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.staff.month.index', [
            'id' => $this->staffUser1->id,
            'year' => $year,
            'month' => $month
            ]));
        $response->assertStatus(200);

        // スタッフ名がタイトルに表示されていること
        $response->assertSee("{$this->staffUser1->name}さんの勤怠");
        // 日付ナビゲーションが表示されていること
        $response->assertSee($expectedMonthDisplay);
        $response->assertSee('前月');
        $response->assertSee('翌月');
        // CSV出力ボタンのフォームが正しく設定されていること
        $response->assertSee('name="user_id" value="' . $this->staffUser1->id . '"', false);
        $response->assertSee('name="year" value="' . $year . '"', false);
        $response->assertSee('name="month" value="' . $month . '"', false);
        $response->assertSee('CSV出力</button>', false);
        // 勤怠データがある日の出勤時刻を検証 (9月25日のデータ)
        $response->assertSee('<td>09:00</td>', false);
        $response->assertSee('<td>18:00</td>', false);
        // 修正後: 詳細ボタンのリンクの核となるURLのみを確認し、HTMLの厳密な整形に依存しないようにする
        $expectedLinkStart = "admin/attendance/{$this->staffUser1->id}?date={$this->testDatePast}&amp;redirect_to=";
        $response->assertSee($expectedLinkStart, false);
        $response->assertSeeText('詳細'); // 詳細ボタンのテキストがあることを確認
    }

    // ID14-3 前月へのナビゲーション（admin.staff.month.index）を検証する。
    public function test_admin_staff_month_index_navigation_to_previous_month()
    {
        // 基準日 2025-09-25 から前月 (2025年8月) のデータをリクエスト
        $targetDate = Carbon::parse($this->testDatePast)->subMonth(); // 2025-08-25
        $year = $targetDate->year;
        $month = $targetDate->month;
        $expectedMonthDisplay = $targetDate->format('Y/m'); // 2025/08

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.staff.month.index', [
                'id' => $this->staffUser1->id,
                'year' => $year,
                'month' => $month
            ]));
        $response->assertStatus(200);

        // 表示されている年月が前月であることを検証 (2025/08)
        $response->assertSee("{$this->staffUser1->name}さんの勤怠");
        $response->assertSee($expectedMonthDisplay);
        // 翌月へのナビゲーションリンクが存在することを確認
        $response->assertSee('翌月');
        // 勤怠データ（2025-09-25）が表示されていないことを検証
        $response->assertDontSee('<td>09:00</td>', false);
        $response->assertDontSee('<td>18:00</td>', false);
        // 前月の勤怠データ（2025-08-20）が正しく表示されていることを検証
        $response->assertSee('<td>08:30</td>', false);
        $response->assertSee('<td>17:30</td>', false);
    }

    // ID14-4 翌月へのナビゲーション（admin.staff.month.index）を検証する。
    public function test_admin_staff_month_index_navigation_to_next_month()
    {
        $targetDate = Carbon::parse($this->testDatePast)->addMonth(); // 2025-10-25
        $year = $targetDate->year;
        $month = $targetDate->month;
        $expectedMonthDisplay = $targetDate->format('Y/m'); // 2025/10

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.staff.month.index', [
                'id' => $this->staffUser1->id,
                'year' => $year,
                'month' => $month
            ]));
        $response->assertStatus(200);

        // 表示されている年月が翌月であることを検証 (2025/10)
        $response->assertSee("{$this->staffUser1->name}さんの勤怠");
        $response->assertSee($expectedMonthDisplay);
        // 前月へのナビゲーションリンクが存在することを確認
        $response->assertSee('前月');
        // 勤怠データ（2025-09-25）が表示されていないことを検証
        $response->assertDontSee('<td>09:00</td>', false);
        $response->assertDontSee('<td>18:00</td>', false);
        // 前月の勤怠データ（2025-08-20）も表示されていないことを検証
        $response->assertDontSee('<td>08:30</td>', false);
        $response->assertDontSee('<td>17:30</td>', false);
    }

    // ID14-5 日次勤怠詳細ページ (admin.user.attendance.detail.index) の表示を検証する。
    public function test_admin_user_attendance_detail_index_prefers_application_data()
    {
        $testDate = $this->testDatePast; // 勤怠/申請データが両方ある日付
        $staffId = $this->staffUser1->id;
        // 戻り先URLを構築 (テストデータの日付から年月を取得)
        $year = Carbon::parse($testDate)->year;
        $month = Carbon::parse($testDate)->month;
        $redirectUrl = route('admin.staff.month.index', ['id' => $staffId, 'year' => $year, 'month' => $month]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.user.attendance.detail.index', [
                'id' => $staffId, // ルートパラメータとしてユーザーIDを渡す
                'date' => $testDate, // クエリパラメータとして日付を渡す
                'redirect_to' => $redirectUrl, // クエリパラメータとしてリダイレクト先を渡す
            ]));
        $response->assertStatus(200);
        $response->assertSee('勤怠詳細');

        // 申請データ: 09:15:00
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('value="09:15"', false);
        // 申請データ: 18:15:00
        $response->assertSee('name="clock_out_time"', false);
        $response->assertSee('value="18:15"', false);
        // 申請データ: 理由
        $response->assertSee('申請による修正');
        // ユーザー名が表示されていること
        $response->assertSee('テストスタッフA');
        // 戻り先URLがhidden fieldに正しく設定されていること
        $response->assertSee('name="redirect_to"', false);
        $response->assertSee('value="' . htmlspecialchars($redirectUrl) . '"', false);
    }
}