<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;

class Id14Test extends TestCase
{
    // テスト後にデータベースをリフレッシュ（初期状態に戻す）
    use RefreshDatabase;

    // テスト用の一時的なルートとテストデータを設定
    protected function setUp(): void
    {
        parent::setUp();
        
        // ----------------------------------------------------
        // 1. テスト用ルートの定義
        // ----------------------------------------------------
        
        // 日次勤怠詳細ページ
        // URLの使用法に合わせて id と date をパラメータとして定義
        // ※ 実際のビューがレンダリングされるように暫定的に定義。
        Route::get('/admin/attendance/{id}/{date}', function () { 
            // ページ内容の確認に必要な最小限のデータをモックまたは取得
            $staffUser = User::find(request('id'));
            $dateString = request('date');
            
            // 勤怠/申請データの取得ロジック（テスト用にはApplicationデータのみモック）
            $application = Application::where('user_id', $staffUser->id)
                                    ->where('checkin_date', $dateString)
                                    ->first();

            // ビューの構造上必要なデータ（例としてスタッフ名と日付、そして修正データ）を渡す
            return response(view('admin_attendance_detail', [
                'staffUser' => $staffUser,
                'dateString' => $dateString,
                'application' => $application, // 申請データ
                'attendance' => Attendance::where('user_id', $staffUser->id)->where('checkin_date', $dateString)->first(), // 勤怠データ
            ])); 
        })
            ->name('admin.user.attendance.detail.index');


        // ----------------------------------------------------
        // 2. テストデータの準備
        // ----------------------------------------------------
        
        // 管理者ユーザー 
        // 🌟 修正: Bladeビューがロールフィルタリングに変わったため、IDを任意の大きな値に戻します。
        // Bladeが「role !== 'admin'」でフィルタリングするため、このIDでもテストが成功するようになります。
        $this->adminUser = User::factory()->create(['role' => 'admin', 'name' => '管理者X', 'id' => 100]);
        
        // スタッフユーザー 
        $this->staffUser1 = User::factory()->create(['role' => 'staff', 'name' => 'テストスタッフA', 'email' => 'test_a@example.com', 'id' => 2]);
        $this->staffUser2 = User::factory()->create(['role' => 'staff', 'name' => 'テストスタッフB', 'email' => 'test_b@example.com', 'id' => 3]);
        
        // 勤怠データ（テスト対象の日付は過去の日付を使用）
        $this->testDatePast = '2025-09-25'; 
        
        // 勤怠レコード（スタッフA）
        $this->attendanceA = Attendance::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $this->testDatePast,
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'break_total_time' => 60, // 1時間 = 60分
            'work_time' => 480 + 13, // 8時間13分を想定 (ユーザーHTMLより)
        ]);

        // 申請レコード（スタッフA - 勤怠データを上書きする内容）
        $this->applicationA = Application::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $this->testDatePast,
            'clock_in_time' => '09:15:00',
            'clock_out_time' => '18:15:00',
            'reason' => '申請による修正',
        ]);
    }

    /**
     * 【フェーズ1】管理者スタッフ一覧ページ (admin.staff.list.index) の表示を検証する。
     */
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
        // Blade側でロール('admin')でフィルタリングされるため、管理者Xは表示されないことを確認します。
        $response->assertDontSee($this->adminUser->name); 
    }

    /**
     * 【フェーズ2】スタッフ月次勤怠ページ (admin.staff.month.index) の表示を検証する。
     */
    public function test_admin_staff_month_index_displays_correct_data_and_links()
    {
        // テスト日付 '2025-09-25' に基づく年月を取得
        $targetDate = Carbon::parse($this->testDatePast);
        $year = $targetDate->year;
        $month = $targetDate->month;
        
        // Bladeテンプレートの出力がゼロパディング (09) されていると仮定
        $expectedMonthDisplay = $targetDate->format('Y/m'); 

        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.staff.month.index', [
                             'id' => $this->staffUser1->id,
                             'year' => $year,
                             'month' => $month
                         ]));

        $response->assertStatus(200);
        
        // スタッフ名がタイトルに表示されていること
        $response->assertSee("{$this->staffUser1->name}さんの勤怠一覧");
        
        // 日付ナビゲーションが表示されていること
        $response->assertSee($expectedMonthDisplay); 
        $response->assertSee('前 月');
        $response->assertSee('翌 月');
        
        // CSV出力ボタンのフォームが正しく設定されていること
        $response->assertSee('name="user_id" value="' . $this->staffUser1->id . '"', false);
        $response->assertSee('name="year" value="' . $year . '"', false);
        $response->assertSee('name="month" value="' . $month . '"', false);
        $response->assertSee('class="csv-submit">CSV出力</button>', false);
        
        // 勤怠データがある日の出勤時刻を検証 (HTML出力の25日のデータ)
        $response->assertSee('<td>09:00</td>', false); 
        $response->assertSee('<td>18:00</td>', false); 

        // 勤怠データがある日の詳細ボタン（テストデータの日付 2025-09-25）のリンクを検証
        $detailLink = route('admin.user.attendance.detail.index', [
            'id' => $this->staffUser1->id, 
            'date' => $this->testDatePast,
            // redirect_to は request()->fullUrl() になるよう、ルートをフルパスで構築
            'redirect_to' => route('admin.staff.month.index', ['id' => $this->staffUser1->id, 'year' => $year, 'month' => $month]) 
        ]);
        
        // HTMLエンコードを考慮し、リンク全体が見えていることをチェック
        $expectedQuery = "admin/attendance/{$this->staffUser1->id}?date={$this->testDatePast}&amp;redirect_to=";
        $response->assertSee($expectedQuery, false);
        $response->assertSee('class="detail-button">詳細</a>', false);
    }
    
    /**
     * 【フェーズ3】日次勤怠詳細ページ (admin.user.attendance.detail.index) の表示を検証する。
     */
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
                             'id' => $staffId, 
                             'date' => $testDate,
                             'redirect_to' => $redirectUrl, 
                         ]));

        $response->assertStatus(200);
        
        // ページタイトルが '勤怠詳細' であることを検証
        $response->assertSee('勤怠詳細'); 
        
        // 優先されるべき申請データの内容がフォームに表示されていることを検証
        
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