<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;


// ID13 勤怠詳細情報取得・修正（管理者）機能のテスト
class Id13Test extends TestCase
{
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

    // ID13-1 日別一覧からのアクセスを想定し、勤怠データと申請データが両方存在する場合、申請データが優先して表示されることを検証する。
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
        ]);

        // 上書きとなる申請データ (Application)
        Application::factory()->create([
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

        // 備考を取得するために、再取得
        $application = Application::where('user_id', $this->staffUser->id)
            ->where('checkin_date', $testDate)
            ->first();


        // 日別勤怠一覧ページからのアクセスをシミュレート
        $redirectUrl = '/admin/daily?date=' . $testDate;

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

        // 出勤時刻
        $response->assertSee('name="clock_in_time"', false);
        $response->assertSee('value="09:15"', false);

        // 退勤時刻
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

    // ID13-1 月別一覧からのアクセスを想定し、勤怠データのみが存在する場合、勤怠データが表示されることを検証する。
    public function test_admin_shows_attendance_data_when_no_application_exists()
    {
        $testDate = '2025-10-02';

        // 勤怠データ (Attendance) のみ作成
        $attendance = Attendance::factory()->create([
            'user_id' => $this->staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '10:00:00',
            'clock_out_time' => '19:00:00',
            'break_time' => [
                ['start' => '13:00:00', 'end' => '14:00:00']
            ],
        ]);

        // 月別勤怠一覧ページからのアクセスをシミュレート
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
        $response->assertSee('name="break_times[0][end_time]"', false); // end_timeもチェック
        $response->assertSee('value="14:00"', false);
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

    // ID13-1 申請データのみが存在する場合、申請データが表示され、attendance_idは送信されないことを検証する。
    public function test_admin_shows_application_if_attendance_is_missing()
    {
        $testDate = '2025-10-03';

        // 申請データ (Application) のみ作成
        $application = Application::factory()->create([
            'user_id' => $this->staffUser->id,
            'checkin_date' => $testDate,
            'clock_in_time' => '08:00:00',
            'clock_out_time' => '17:00:00',
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

    // ID13-1 勤怠データも申請データも存在しない場合、空のフォームが表示されることを検証する。
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
        $response->assertSeeInOrder(['name="break_times[0][start_time]"', 'value=""'], false);
        $response->assertSeeInOrder(['name="break_times[0][end_time]"', 'value=""'], false);
        $response->assertDontSee('name="break_times[1][start_time]"', false); // 2つ目のフォームが存在しないことを確認

        // 備考欄が空であること (空のtextareaタグの開始と終了を確認)
        $response->assertSee('name="reason"', false);
        $response->assertSee('<textarea name="reason"', false);
        $response->assertSee('</textarea>', false);

        // hidden fields
        $response->assertDontSee('name="attendance_id"', false); // 勤怠IDの非存在
        $response->assertSee('name="user_id" value="' . $this->staffUser->id . '"', false); // ユーザーIDの存在
    }

    // ID13-2 管理者による修正、出勤時間が退勤時間の後になっている場合（日跨ぎ設定１８時間以上）の場合のエラーのテスト。
    public function test_admin_time_order_validation_messages()
    {
        $testDate = '2025-10-05';

        // 10:00 出勤, 09:00 退勤。日跨ぎではないと判定されるパターン
        $invalidData = [
            'checkin_date' => $testDate,
            'user_id' => $this->staffUser->id,
            'clock_in_time' => '10:00',
            'clock_out_time' => '09:00',
            'reason' => 'Test Reason',
            'break_times' => [],
        ];

        $updateUrl = route('admin.attendance.approve');

        $response = $this->actingAs($this->adminUser)
            ->post($updateUrl, $invalidData);

        $response->assertRedirect();

        $expectedMessage = '出勤時間もしくは退勤時間が不適切な値です。';
        $response->assertSessionHasErrorsIn('default', [
            'clock_in_time' => $expectedMessage,
            'clock_out_time' => $expectedMessage,
        ]);
    }

    // ID13-3,4 【バリデーション検証】休憩時間の順序および勤務時間との境界チェックのエラーメッセージを検証する。
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

        // 休憩時間の不正に関する汎用的なメッセージ
        $genericBreakTimeMessage = '休憩時間が不適切な値です。';
        // 休憩終了時刻が退勤時刻を超えた場合などに出る可能性のあるメッセージ
        $breakTimeOrClockOutMessage = '休憩時間もしくは退勤時間が不適切な値です。';

        // 休憩の順序不正 (開始 >= 終了: 例 14:00 - 13:00)
        $invalidBreakOrderData = array_merge($baseData, [
            'break_times' => [
                ['start_time' => '14:00', 'end_time' => '13:00']
            ],
        ]);

        $responseA = $this->actingAs($this->adminUser)
            ->post($updateUrl, $invalidBreakOrderData);

        $responseA->assertRedirect();

        $responseA->assertSessionHasErrorsIn('default', [
            'break_times.0.start_time' => $genericBreakTimeMessage,
        ]);

        // 休憩開始が出勤時刻より前 (例 09:00 - 11:00)
        $invalidBreakStartBoundaryData = array_merge($baseData, [
            'break_times' => [
                ['start_time' => '09:00', 'end_time' => '11:00'] // 09:00 < 10:00
            ],
        ]);

        $responseB = $this->actingAs($this->adminUser)
            ->post($updateUrl, $invalidBreakStartBoundaryData);

        $responseB->assertRedirect();
        $responseB->assertSessionHasErrorsIn('default', [
            'break_times.0.start_time' => $genericBreakTimeMessage,
        ]);

        // 休憩終了が退勤時刻より後 (例 18:30 - 19:30)
        $invalidBreakEndBoundaryData = array_merge($baseData, [
            'break_times' => [
                ['start_time' => '18:30', 'end_time' => '19:30'] // 19:30 > 19:00
            ],
        ]);

        $responseC = $this->actingAs($this->adminUser)
            ->post($updateUrl, $invalidBreakEndBoundaryData);

        $responseC->assertRedirect();
        $responseC->assertSessionHasErrorsIn('default', [
            'break_times.0.end_time' => $breakTimeOrClockOutMessage,
        ]);
    }

    // ID13-5 備考が未入力の場合エラーメッセージが表示される。
    public function test_reason_required_message()
    {
        $testDate = '2025-10-07';

        $invalidData = [
            'checkin_date' => $testDate,
            'user_id' => $this->staffUser->id,
            'clock_in_time' => '10:00',
            'clock_out_time' => '19:00',
            'reason' => '', // 必須フィールドを空にする
            'break_times' => [],
        ];

        $updateUrl = route('admin.attendance.approve');

        $response = $this->actingAs($this->adminUser)

            ->post($updateUrl, $invalidData);

        $response->assertRedirect();
        $response->assertSessionHasErrorsIn('default', [
            'reason' => '備考を記入してください。',
        ]);
    }
}