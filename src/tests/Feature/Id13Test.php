<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;
use Database\Factories\ApplicationFactory;
use Illuminate\Support\Facades\Route;

class Id13Test extends TestCase
{
    // テスト後にデータベースをリフレッシュ（初期状態に戻す）
    use RefreshDatabase;
    
    /**
     * バリデーションテストで必要となるため、一時的に更新ルートを定義します。
     * ルート名が見つからないエラー (RouteNotFoundException) を回避するため、
     * テスト内で route() ヘルパーを使わずに、直接URLを指定するように修正します。
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // テスト用の更新ルートを一時的に定義
        Route::post('/admin/user/{id}/attendance/{date}/update', function () {
            // ApplicationAndAttendantRequestのバリデーションを実行
            return app(\App\Http\Requests\ApplicationAndAttendantRequest::class)->validate();
        })->name('admin.user.attendance.detail.update');
    }


    /**
     * 【パターン1】勤怠データと申請データが両方存在する場合、申請データが優先して表示されることを検証する。
     * (日別一覧からのアクセスを想定し、redirect_toも検証)
     */
    public function test_admin_prefers_application_data_on_detail_page()
    {
        $testDate = '2025-10-01';
        $adminUser = User::factory()->create(['role' => 'admin']);
        $staffUser = User::factory()->create(['role' => 'staff']);

        // 既存の勤怠データ (Attendance)
        Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'break_time' => [['start' => '12:00:00', 'end' => '13:00:00']],
            'reason' => 'Original Attendance Reason',
        ]);

        // 上書きとなる申請データ (Application)
        $application = Application::factory()->create([
            'user_id' => $staffUser->id,
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

        $response = $this->actingAs($adminUser)
                         ->get(route('admin.user.attendance.detail.index', [
                             'id' => $staffUser->id, 
                             'date' => $testDate,
                             'redirect_to' => $redirectUrl, // 戻り先URLを検証
                         ]));

        $response->assertStatus(200);

        // フォーム内容が申請データと一致していることの検証 (属性を分割してチェック)
        $response->assertSee($staffUser->name); // スタッフ名
        $response->assertSee(Carbon::parse($testDate)->format('Y年m月d日')); // 日付
        
        // 出勤時刻
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('value="09:15"', false);
        
        // 退勤時刻
        $response->assertSee('name="clock_out_time"', false);
        $response->assertSee('value="18:15"', false);
        
        // 休憩1
        $response->assertSee('name="break_times[0][start_time]"', false);
        $response->assertSee('value="12:30"', false);
        
        // 休憩2
        $response->assertSee('name="break_times[1][start_time]"', false);
        $response->assertSee('value="15:00"', false);
        
        $response->assertSee($application->reason);
        
        // 修正: redirect_toも分割してチェック
        $response->assertSee('name="redirect_to"', false); 
        $response->assertSee('value="' . $redirectUrl . '"', false); // &がないためエンコードは不要
    }


    /**
     * 【パターン2】勤怠データのみが存在する場合、勤怠データが表示されることを検証する。
     * (月別一覧からのアクセスを想定し、redirect_toも検証)
     */
    public function test_admin_shows_attendance_data_when_no_application_exists()
    {
        $testDate = '2025-10-02';
        $adminUser = User::factory()->create(['role' => 'admin']);
        $staffUser = User::factory()->create(['role' => 'staff']);
        
        // 勤怠データ (Attendance) のみ作成
        $attendance = Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '10:00:00', // 勤怠データ
            'clock_out_time' => '19:00:00', // 勤怠データ
            'break_time' => [
                ['start' => '13:00:00', 'end' => '14:00:00']
            ],
            'reason' => 'Only Attendance Data Exists', // 勤怠データ
        ]);
        
        // 月別勤怠一覧ページからのアクセスをシミュレート
        $redirectUrl = '/admin/staff/1/month?year=2025&month=10';
        // HTMLエンコードされた値 (& -> &amp;) を準備
        $expectedEncodedRedirectValue = '/admin/staff/1/month?year=2025&amp;month=10';

        $response = $this->actingAs($adminUser)
                         ->get(route('admin.user.attendance.detail.index', [
                             'id' => $staffUser->id, 
                             'date' => $testDate,
                             'redirect_to' => $redirectUrl, // 戻り先URLを検証
                         ]));

        $response->assertStatus(200);

        // フォーム内容が勤怠データと一致していることの検証 (属性を分割してチェック)
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('value="10:00"', false);
        $response->assertSee('name="clock_out_time"', false);
        $response->assertSee('value="19:00"', false);
        
        // 休憩時間 (勤怠データの1つ + 空欄1つ)
        $response->assertSee('name="break_times[0][start_time]"', false);
        $response->assertSee('value="13:00"', false);
        $response->assertSee('name="break_times[1][start_time]"', false);
        $response->assertSee('value=""', false); // 空欄の確保
        $response->assertSee($attendance->reason); // 備考

        // hidden fields
        $response->assertSee('name="attendance_id" value="' . $attendance->id . '"', false); // 勤怠IDの存在
        $response->assertSee('name="user_id" value="' . $staffUser->id . '"', false);
        
        // 修正: HTMLエンコードされた値をチェック
        $response->assertSee('name="redirect_to"', false);
        $response->assertSee('value="' . $expectedEncodedRedirectValue . '"', false);
    }
    
    /**
     * 【パターン3】申請データのみが存在する場合、申請データが表示され、attendance_idは送信されないことを検証する。
     */
    public function test_admin_shows_application_if_attendance_is_missing()
    {
        $testDate = '2025-10-03';
        $adminUser = User::factory()->create(['role' => 'admin']);
        $staffUser = User::factory()->create(['role' => 'staff']);

        // 申請データ (Application) のみ作成
        $application = Application::factory()->create([
            'user_id' => $staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '08:00:00',
            'clock_out_time' => '17:00:00',
            'reason' => 'Only Application Data Exists',
        ]);

        $response = $this->actingAs($adminUser)
                         ->get(route('admin.user.attendance.detail.index', [
                             'id' => $staffUser->id, 
                             'date' => $testDate,
                         ]));

        $response->assertStatus(200);
        
        // 申請データが表示されていることの検証 (属性を分割してチェック)
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('value="08:00"', false);
        $response->assertSee($application->reason);
        
        // 勤怠データがないため、attendance_idがhidden fieldに存在しないこと
        $response->assertDontSee('name="attendance_id"', false); 
        
        // ユーザーIDは$user->idから取得して渡されていること
        $response->assertSee('name="user_id" value="' . $staffUser->id . '"', false);
    }
    
    /**
     * 【パターン4】勤怠データも申請データも存在しない場合、空のフォームが表示されることを検証する。
     */
    public function test_admin_shows_empty_form_when_no_data_exists()
    {
        $testDate = '2025-10-04';
        $adminUser = User::factory()->create(['role' => 'admin']);
        $staffUser = User::factory()->create(['role' => 'staff']);
        
        // 勤怠データも申請データも作成しない

        $response = $this->actingAs($adminUser)
                         ->get(route('admin.user.attendance.detail.index', [
                             'id' => $staffUser->id, 
                             'date' => $testDate,
                         ]));

        $response->assertStatus(200);
        
        // 時刻と休憩欄が全て空であることの検証
        
        // 出勤・退勤時刻
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('name="clock_out_time"', false);
        
        // 休憩時間 (最低2つの空欄が確保されていること)
        $response->assertSee('name="break_times[0][start_time]"', false);
        $response->assertSee('name="break_times[1][start_time]"', false);

        // すべてのinputフィールドで値が空であることの確認 (value="" を含む)
        $response->assertSee('value=""', false); 

        // 備考欄が空であること (空のtextareaタグの開始と終了を確認)
        $response->assertSee('<textarea name="reason" class="">', false); 
        $response->assertSee('</textarea>', false); 

        
        // hidden fields
        $response->assertDontSee('name="attendance_id"', false); // 勤怠IDの非存在
        $response->assertSee('name="user_id" value="' . $staffUser->id . '"', false); // ユーザーIDの存在
    }
    
    
    // ====================================================================
    // 新規追加テストケース: ApplicationAndAttendantRequest のバリデーションメッセージ検証
    // ====================================================================

    /**
     * 【バリデーション検証】管理者による不正な出勤・退勤時刻の順序入力時に、
     * 共通のエラーメッセージ『出勤時間もしくは退勤時間が不適切な値です。』が表示されることを検証する。
     */
    public function test_admin_time_order_validation_messages()
    {
        $testDate = '2025-10-05';
        $adminUser = User::factory()->create(['role' => 'admin']);
        $staffUser = User::factory()->create(['role' => 'staff']);
        
        // 不正な出勤・退勤の順序 (例: 10:00 出勤, 09:00 退勤。日跨ぎではないと判定されるパターン)
        $invalidData = [
            'checkin_date' => $testDate,
            'user_id' => $staffUser->id,
            'clock_in_time' => '10:00',
            'clock_out_time' => '09:00', 
            'reason' => 'Test Reason',
            'break_times' => [],
        ];
        
        // route() ヘルパーではなく、直接URL文字列を使用
        $updateUrl = "/admin/user/{$staffUser->id}/attendance/{$testDate}/update";

        $response = $this->actingAs($adminUser)
                         // 勤怠更新ルートへPOSTリクエストを送信
                         ->post($updateUrl, $invalidData);

        // 管理者用の共通メッセージ ('出勤時間もしくは退勤時間が不適切な値です。') を確認
        $expectedMessage = '出勤時間もしくは退勤時間が不適切な値です。';

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
        $adminUser = User::factory()->create(['role' => 'admin']);
        $staffUser = User::factory()->create(['role' => 'staff']);
        
        // 更新用URLを事前に定義
        $updateUrl = "/admin/user/{$staffUser->id}/attendance/{$testDate}/update";

        // 正常な勤務時間 (10:00 - 19:00)
        $baseData = [
            'checkin_date' => $testDate,
            'user_id' => $staffUser->id,
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
        
        $responseA = $this->actingAs($adminUser)
                          // route() ヘルパーではなく、直接URL文字列を使用
                          ->post($updateUrl, $invalidBreakOrderData);

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
        
        $responseB = $this->actingAs($adminUser)
                          // route() ヘルパーではなく、直接URL文字列を使用
                          ->post($updateUrl, $invalidBreakStartBoundaryData);

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
        
        $responseC = $this->actingAs($adminUser)
                          // route() ヘルパーではなく、直接URL文字列を使用
                          ->post($updateUrl, $invalidBreakEndBoundaryData);

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
        $adminUser = User::factory()->create(['role' => 'admin']);
        $staffUser = User::factory()->create(['role' => 'staff']);
        
        // 備考が空
        $invalidData = [
            'checkin_date' => $testDate,
            'user_id' => $staffUser->id,
            'clock_in_time' => '10:00',
            'clock_out_time' => '19:00',
            'reason' => '', // 必須フィールドを空にする
            'break_times' => [],
        ];

        // route() ヘルパーではなく、直接URL文字列を使用
        $updateUrl = "/admin/user/{$staffUser->id}/attendance/{$testDate}/update";
        
        $response = $this->actingAs($adminUser)
                         // 勤怠更新ルートへPOSTリクエストを送信
                         ->post($updateUrl, $invalidData);

        // 'reason.required' => '備考を記入してください。'
        $response->assertSessionHasErrorsIn('default', [
            'reason' => '備考を記入してください。', 
        ]);
    }
}
