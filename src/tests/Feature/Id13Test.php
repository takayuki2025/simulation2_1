<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;

class Id13Test extends TestCase
{
    // テスト後にデータベースをリフレッシュ（初期状態に戻す）
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $adminUser;

    /** @var \App\Models\User */
    protected $staffUser;
    
    /**
     * 各テスト実行前に必要なセットアップを実行
     */
    protected function setUp(): void
    {
        parent::setUp();
        // 共通の管理者ユーザーと一般ユーザーを作成し、プロパティに格納
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->staffUser = User::factory()->create(['role' => 'staff']);
    }
    
    /*
     * NOTE: バリデーション検証のテストは、実際のアプリケーションで定義されているPOSTルート
     * (/admin/attendance/approve) に依存します。
     */


    /**
     * 【パターン1】勤怠データと申請データが両方存在する場合、申請データが優先して表示されることを検証する。
     * (日別一覧からのアクセスを想定し、redirect_toも検証)
     */
    public function test_admin_prefers_application_data_on_detail_page()
    {
        $testDate = '2025-10-01';
        // 既存の勤怠データ (Attendance)
        Attendance::factory()->create([
            'user_id' => $this->staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'break_time' => [['start' => '12:00:00', 'end' => '13:00:00']],
            'reason' => 'Original Attendance Reason',
        ]);

        // 上書きとなる申請データ (Application)
        $application = Application::factory()->create([
            'user_id' => $this->staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '09:15:00', // 優先されるデータ
            'clock_out_time' => '18:15:00', // 優先されるデータ
            'break_time' => [
                ['start' => '12:30:00', 'end' => '13:30:00'],
                ['start' => '15:00:00', 'end' => '15:15:00'],
            ],
            'reason' => 'Application Reason Override', // 優先されるデータ
        ]);

        // 日別勤怠一覧ページからのアクセスをシミュレート
        $redirectUrl = '/admin/daily?date=' . $testDate; 

        // ルート名 'admin.user.attendance.detail.index' が定義されていることを前提とする
        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.user.attendance.detail.index', [
                             'id' => $this->staffUser->id, 
                             'date' => $testDate,
                             'redirect_to' => $redirectUrl, // 戻り先URLを検証
                         ]));

        $response->assertStatus(200);

        // フォーム内容が申請データと一致していることの検証
        $response->assertSee($this->staffUser->name); // スタッフ名
        
        // 日付の表示形式を検証
        $response->assertSee(Carbon::parse($testDate)->format('Y年'));
        $response->assertSee(Carbon::parse($testDate)->format('n月j日'));
        
        // 出勤時刻 (type="text" でアサート)
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('value="09:15"', false);
        
        // 退勤時刻 (type="text" でアサート)
        $response->assertSee('name="clock_out_time"', false);
        $response->assertSee('value="18:15"', false);
        
        // 休憩1 (既存データ - type="text" でアサート)
        $response->assertSee('name="break_times[0][start_time]"', false);
        $response->assertSee('value="12:30"', false);
        
        // 休憩2 (既存データ - type="text" でアサート)
        $response->assertSee('name="break_times[1][start_time]"', false);
        $response->assertSee('value="15:00"', false);

        // 休憩3 (空欄の確保 - type="text" でアサート)
        $response->assertSee('name="break_times[2][start_time]"', false);
        $response->assertSee('value=""', false); 

        // 休憩4 (存在しないことを確認)
        $response->assertDontSee('name="break_times[3][start_time]"', false);
        
        // 備考
        $response->assertSee($application->reason);
        
        // redirect_to (HTMLエンコードされた値 'value="/admin/daily?date=2025-10-01"' を確認)
        $response->assertSee('name="redirect_to"', false); 
        $response->assertSee('value="' . $redirectUrl . '"', false);
    }


    /**
     * 【パターン2】勤怠データのみが存在する場合、勤怠データが表示されることを検証する。
     * (月別一覧からのアクセスを想定し、redirect_toも検証)
     */
    public function test_admin_shows_attendance_data_when_no_application_exists()
    {
        $testDate = '2025-10-02';
        
        // 勤怠データ (Attendance) のみ作成
        $attendance = Attendance::factory()->create([
            'user_id' => $this->staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '10:00:00', // 勤怠データ
            'clock_out_time' => '19:00:00', // 勤怠データ
            'break_time' => [
                ['start' => '13:00:00', 'end' => '14:00:00']
            ],
            'reason' => 'Only Attendance Data Exists', // 勤怠データ
        ]);
        
        // 月別勤怠一覧ページからのアクセスをシミュレート
        // UserID=1を仮定してテスト内のリダイレクトURLを作成（テスト実行時のStaffIDに依存しない）
        $redirectUrl = '/admin/staff/' . $this->staffUser->id . '/month?year=2025&month=10';
        
        // HTMLエンコードされた値 (& -> &amp;) を準備
        // フォームのvalue属性では&が&amp;にエンコードされるため
        $expectedEncodedRedirectValue = '/admin/staff/' . $this->staffUser->id . '/month?year=2025&amp;month=10';

        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.user.attendance.detail.index', [
                             'id' => $this->staffUser->id, 
                             'date' => $testDate,
                             'redirect_to' => $redirectUrl, // 戻り先URLを検証
                         ]));

        $response->assertStatus(200);

        // 日付の表示形式を検証
        $response->assertSee(Carbon::parse($testDate)->format('Y年'));
        $response->assertSee(Carbon::parse($testDate)->format('n月j日'));

        // フォーム内容が勤怠データと一致していることの検証 (type="text" でアサート)
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('value="10:00"', false);
        $response->assertSee('name="clock_out_time"', false);
        $response->assertSee('value="19:00"', false);
        
        // 休憩時間 (勤怠データの1つ + 空欄1つ) (type="text" でアサート)
        $response->assertSee('name="break_times[0][start_time]"', false);
        $response->assertSee('value="13:00"', false);
        $response->assertSee('name="break_times[1][start_time]"', false);
        $response->assertSee('value=""', false); // 空欄の確保
        $response->assertDontSee('name="break_times[2][start_time]"', false); // 3つ目が存在しないことを確認
        $response->assertSee($attendance->reason); // 備考

        // hidden fields
        $response->assertSee('name="attendance_id" value="' . $attendance->id . '"', false); // 勤怠IDの存在
        $response->assertSee('name="user_id" value="' . $this->staffUser->id . '"', false);
        
        // HTMLエンコードされた値をチェック
        $response->assertSee('name="redirect_to"', false);
        $response->assertSee('value="' . $expectedEncodedRedirectValue . '"', false);
    }
    
    /**
     * 【パターン3】申請データのみが存在する場合、申請データが表示され、attendance_idは送信されないことを検証する。
     */
    public function test_admin_shows_application_if_attendance_is_missing()
    {
        $testDate = '2025-10-03';

        // 申請データ (Application) のみ作成
        $application = Application::factory()->create([
            'user_id' => $this->staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '08:00:00',
            'clock_out_time' => '17:00:00',
            // 休憩時間1つを明示的に設定
            'break_time' => [['start' => '12:00:00', 'end' => '13:00:00']], 
            'reason' => 'Only Application Data Exists',
        ]);

        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.user.attendance.detail.index', [
                             'id' => $this->staffUser->id, 
                             'date' => $testDate,
                         ]));

        $response->assertStatus(200);
        
        // 申請データが表示されていることの検証 (type="text" でアサート)
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('value="08:00"', false);
        $response->assertSee($application->reason);
        
        // 勤怠データがないため、attendance_idがhidden fieldに存在しないこと
        $response->assertDontSee('name="attendance_id"', false); 
        
        // ユーザーIDは$user->idから取得して渡されていること
        $response->assertSee('name="user_id" value="' . $this->staffUser->id . '"', false);

        // 休憩時間 (1つの既存データと1つの空欄が確保されていること) (type="text" でアサート)
        $response->assertSee('name="break_times[0][start_time]"', false);
        $response->assertSee('value="12:00"', false); // 既存データ
        $response->assertSee('name="break_times[1][start_time]"', false);
        $response->assertSee('value=""', false); // 空欄の確保
        $response->assertDontSee('name="break_times[2][start_time]"', false); // 3つ目が存在しないことを確認
    }
    
    /**
     * 【パターン4】勤怠データも申請データも存在しない場合、空のフォームが表示されることを検証する。
     */
    public function test_admin_shows_empty_form_when_no_data_exists()
    {
        $testDate = '2025-10-04';
        
        // 勤怠データも申請データも作成しない

        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.user.attendance.detail.index', [
                             'id' => $this->staffUser->id, 
                             'date' => $testDate,
                         ]));

        $response->assertStatus(200);
        
        // 時刻と休憩欄が全て空であることの検証
        
        // 出勤・退勤時刻がvalue=""で空であること (type="text" でアサート)
        // ビュー側の改行や空白に影響されないよう、name属性とvalue=""属性が連続して存在することを確認します。
        $response->assertSeeInOrder(['name="clock_in_time"', 'value=""'], false);
        $response->assertSeeInOrder(['name="clock_out_time"', 'value=""'], false);
        
        // 休憩時間 (常に1つの空欄が確保されていること) (type="text" でアサート)
        $response->assertSee('name="break_times[0][start_time]"', false);
        // こちらも同様に、name属性とvalue=""属性が連続して存在することを確認します。
        $response->assertSeeInOrder(['name="break_times[0][start_time]"', 'value=""'], false);
        $response->assertSeeInOrder(['name="break_times[0][end_time]"', 'value=""'], false);
        $response->assertDontSee('name="break_times[1][start_time]"', false); // 2つ目のフォームが存在しないことを確認

        // 備考欄が空であること (空のtextareaタグの開始と終了を確認)
        // HTMLのレンダリングによって、`<textarea>`と`</textarea>`の間に改行やスペースが入る可能性があるため、
        // フォームがそのように存在することを確認します。
        $response->assertSee('name="reason"', false); 
        $response->assertSee('<textarea name="reason"', false);
        $response->assertSee('</textarea>', false);
        
        // hidden fields
        $response->assertDontSee('name="attendance_id"', false); // 勤怠IDの非存在
        $response->assertSee('name="user_id" value="' . $this->staffUser->id . '"', false); // ユーザーIDの存在
    }
    
    
    // ====================================================================
    // バリデーション検証: ApplicationAndAttendantRequest
    // ルートをadmin.attendance.approveに変更
    // ====================================================================

    /**
     * 【バリデーション検証】管理者による不正な出勤・退勤時刻の順序入力時に、
     * 共通のエラーメッセージ『出勤時間もしくは退勤時間が不適切な値です。』が表示されることを検証する。
     */
    public function test_admin_time_order_validation_messages()
    {
        $testDate = '2025-10-05';
        
        // 不正な出勤・退勤の順序 (例: 10:00 出勤, 09:00 退勤。日跨ぎではないと判定されるパターン)
        $invalidData = [
            'checkin_date' => $testDate,
            'user_id' => $this->staffUser->id,
            'clock_in_time' => '10:00',
            'clock_out_time' => '09:00', 
            'reason' => 'Test Reason',
            'break_times' => [],
        ];
        
        // 正しいPOSTルートを使用
        $updateUrl = route('admin.attendance.approve');

        // POSTリクエストを送信
        $response = $this->actingAs($this->adminUser)
                         ->post($updateUrl, $invalidData);

        // バリデーション失敗時のリダイレクトをチェック
        $response->assertRedirect();
        
        // セッションにエラーメッセージが格納されていることを確認
        $expectedMessage = '出勤時間もしくは退勤時間が不適切な値です。';
        // 'clock_in_time'と'clock_out_time'の両方にエラーが付与される想定
        $response->assertSessionHasErrorsIn('default', [
            'clock_in_time' => $expectedMessage,
            'clock_out_time' => $expectedMessage,
        ]);
    }

    /**
     * 【バリデーション検証】休憩時間の順序および勤務時間との境界チェックのエラーメッセージを検証する。
     */
    public function test_break_time_validation_messages()
    {
        $testDate = '2025-10-06';
        
        // 正しいPOSTルートを使用
        $updateUrl = route('admin.attendance.approve');

        // 正常な勤務時間 (10:00 - 19:00)
        $baseData = [
            'checkin_date' => $testDate,
            'user_id' => $this->staffUser->id,
            'clock_in_time' => '10:00',
            'clock_out_time' => '19:00',
            'reason' => 'Test Reason',
        ];
        
        // --- シナリオ A: 休憩の順序不正 (開始 >= 終了: 例 14:00 - 13:00) ---
        $invalidBreakOrderData = array_merge($baseData, [
            'break_times' => [
                ['start_time' => '14:00', 'end_time' => '13:00'] 
            ],
        ]);
        
        $responseA = $this->actingAs($this->adminUser)
                          ->post($updateUrl, $invalidBreakOrderData);

        $responseA->assertRedirect();
        // 'break_times.*.start_time.before' => '休憩時間が不適切な値です。'
        $responseA->assertSessionHasErrorsIn('default', [
            'break_times.0.start_time' => '休憩時間が不適切な値です。',
        ]);
        
        // --- シナリオ B: 休憩開始が出勤時刻より前 (例 09:00 - 11:00) ---
        $invalidBreakStartBoundaryData = array_merge($baseData, [
            'break_times' => [
                ['start_time' => '09:00', 'end_time' => '11:00'] // 09:00 < 10:00
            ],
        ]);
        
        $responseB = $this->actingAs($this->adminUser)
                          ->post($updateUrl, $invalidBreakStartBoundaryData);

        $responseB->assertRedirect();
        // 'break_times.*.start_time.after_or_equal' => '休憩開始時刻は、出勤時刻以降に設定してください。'
        $responseB->assertSessionHasErrorsIn('default', [
            'break_times.0.start_time' => '休憩開始時刻は、出勤時刻以降に設定してください。',
        ]);
        
        // --- シナリオ C: 休憩終了が退勤時刻より後 (例 18:30 - 19:30) ---
        $invalidBreakEndBoundaryData = array_merge($baseData, [
            'break_times' => [
                ['start_time' => '18:30', 'end_time' => '19:30'] // 19:30 > 19:00
            ],
        ]);
        
        $responseC = $this->actingAs($this->adminUser)
                          ->post($updateUrl, $invalidBreakEndBoundaryData);

        $responseC->assertRedirect();
        // 'break_times.*.end_time.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です。'
        $responseC->assertSessionHasErrorsIn('default', [
            'break_times.0.end_time' => '休憩時間もしくは退勤時間が不適切な値です。',
        ]);
    }

    /**
     * 【バリデーション検証】備考 (reason) が空の場合に、
     * 『備考を記入してください。』が表示されることを検証する。
     */
    public function test_reason_required_message()
    {
        $testDate = '2025-10-07';
        
        // 備考が空
        $invalidData = [
            'checkin_date' => $testDate,
            'user_id' => $this->staffUser->id,
            'clock_in_time' => '10:00',
            'clock_out_time' => '19:00',
            'reason' => '', // 必須フィールドを空にする
            'break_times' => [],
        ];

        // 正しいPOSTルートを使用
        $updateUrl = route('admin.attendance.approve');
        
        $response = $this->actingAs($this->adminUser)
                         // 勤怠更新ルートへPOSTリクエストを送信
                         ->post($updateUrl, $invalidData);

        $response->assertRedirect();
        // 'reason.required' => '備考を記入してください。'
        $response->assertSessionHasErrorsIn('default', [
            'reason' => '備考を記入してください。', 
        ]);
    }
}
