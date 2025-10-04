<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Application;
use App\Models\Attendance;
use Carbon\Carbon;


// ID11 勤怠詳細情報修正（一般ユーザー）機能のテスト
class Id11Test extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $validData;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. ユーザー作成
        $this->user = User::factory()->create(['role' => 'employee']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        // 2. 成功するリクエストのベースデータ (ID11 Valid Data)
        // 勤務時間: 9:00 -> 18:00 (9時間 = 540分)
        // 休憩時間: 12:00 -> 13:00 (60分)
        // 勤務時間合計（実働）: 540 - 60 = 480分 (8時間)
        // 休憩時間合計: 60分 (1時間)
        $this->validData = [
            'attendance_id' => null,
            'user_id' => $this->user->id,
            'checkin_date' => '2023-10-27',
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'reason' => 'テストのための修正理由です。',
            'break_times' => [
                ['start_time' => '12:00', 'end_time' => '13:00'],
            ],
            // 💡 修正: データベースのNULL制約を満たすため、計算後の値をテストデータに追加
            'work_time' => 480, 
            'break_total_time' => 60,
        ];
    }

    // ====================================================================
    // ID11: 勤怠修正申請のバリデーションテスト
    // ====================================================================

    /**
     * 【検証 1】必須フィールド（出勤時刻、退勤時刻、備考）の欠落をチェック。
     */
    public function test_required_fields_check()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '';
        $invalidData['clock_out_time'] = '';
        $invalidData['reason'] = '';

        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $invalidData);

        $response->assertSessionHasErrors([
            'clock_in_time' => '出勤時刻を入力してください。',
            'clock_out_time' => '退勤時刻を入力してください。',
            'reason' => '備考を記入してください。',
        ]);
    }

    /**
     * 【検証 2】出勤時刻が退勤時刻より後になっている順序エラーをチェック。
     */
    public function test_clock_in_after_clock_out_fails()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '19:00'; // 18:00より後
        $invalidData['clock_out_time'] = '18:00';

        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $invalidData);

        // バリデーションメッセージは実装に依存するが、ここでは'clock_in_time'が原因であることを確認
        $response->assertSessionHasErrors([
            'clock_in_time' => '出勤時刻が不適切な値です。',
        ]);
    }

    /**
     * 【検証 3】退勤時刻が出勤時刻より前になっている順序エラーをチェック。
     */
    public function test_clock_out_before_clock_in_fails()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '09:00';
        $invalidData['clock_out_time'] = '08:00'; // 09:00より前

        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $invalidData);

        // バリデーションメッセージは実装に依存するが、ここでは'clock_out_time'が原因であることを確認
        $response->assertSessionHasErrors([
            'clock_out_time' => '退勤時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 4】休憩開始時刻が退勤時刻より後に入力された場合。
     */
    public function test_break_start_after_clock_out_fails()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['start_time'] = '19:00';
        $invalidData['clock_out_time'] = '18:00';

        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $invalidData);

        // 休憩開始時間が業務時間外であることを確認
        $response->assertSessionHasErrors([
            'break_times.0.start_time' => '休憩時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 5】休憩終了時刻が出勤時刻より前に入力された場合。
     */
    public function test_break_end_before_clock_in_fails()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['end_time'] = '08:00';
        $invalidData['clock_in_time'] = '09:00';

        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $invalidData);

        // 休憩終了時間が業務時間外であることを確認
        $response->assertSessionHasErrors([
            'break_times.0.end_time' => '休憩時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 6】休憩時間が逆転して入力された場合。
     */
    public function test_break_times_are_reversed_fails()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['start_time'] = '14:00';
        $invalidData['break_times'][0]['end_time'] = '13:00';

        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $invalidData);

        // 休憩の開始と終了が逆転していることを確認
        $response->assertSessionHasErrors([
            'break_times.0.start_time' => '休憩時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 7】休憩開始時刻が出勤時刻より前に入力された場合 (after_or_equalテスト)。
     */
    public function test_break_start_before_or_at_clock_in_boundary_check()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['start_time'] = '08:00';
        $invalidData['break_times'][0]['end_time'] = '08:30';
        $invalidData['clock_in_time'] = '09:00'; // 9:00出勤

        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $invalidData);

        $response->assertSessionHasErrors([
            // 休憩開始 8:00 は出勤 9:00 より前なのでエラー
            'break_times.0.start_time' => '休憩開始時刻は、出勤時刻以降に設定してください。',
        ]);
    }

    /**
     * 【検証 8】休憩終了時刻が退勤時刻より後に入力された場合 (before_or_equalテスト)。
     */
    public function test_break_end_after_or_at_clock_out_boundary_check()
    {
        $invalidData = $this->validData;
        $invalidData['clock_out_time'] = '18:00'; // 18:00退勤
        $invalidData['break_times'][0]['start_time'] = '18:00';
        $invalidData['break_times'][0]['end_time'] = '18:30'; // 18:00退勤より後

        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $invalidData);

        $response->assertSessionHasErrors([
            // 休憩終了 18:30 は退勤 18:00 より後なのでエラー
            'break_times.0.end_time' => '休憩時間もしくは退勤時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 9】全ての時刻が正しい場合（成功ケース）。
     */
    public function test_valid_data_passes_validation()
    {
        // route()ヘルパーを使用
        $response = $this->actingAs($this->user)->post(route('application.create'), $this->validData);

        // 成功時にはリダイレクトかステータス302を確認し、セッションエラーがないことを検証
        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
    }

    // ====================================================================
    // ID12: 管理者申請一覧・日跨ぎ補正・一般ユーザーリストテスト
    // ====================================================================
    
    /**
     * ユーザーが日跨ぎを含む申請を作成し、管理者の承認待ち一覧に正しく表示されるかを確認する。
     */
    public function test_admin_sees_newly_created_pending_application_with_cross_day_correction()
    {
        $date = '2025-10-27'; // 申請対象日
        $reason = '夜勤明けのため、退勤時間が翌日になっています。'; // 特定のためのユニークな理由
        
        // 日跨ぎの計算
        // 業務時間: 22:00 (10/27) から 06:00 (10/28) まで = 8時間 (480分)
        // 休憩: 02:00 (10/28) から 03:00 (10/28) まで = 1時間 (60分)
        // 勤務時間合計（実働）: 480 - 60 = 420分
        // 休憩時間合計: 60分

        // 1. 申請対象日のAttendanceレコードをまず作成する
        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $date,
            'clock_in_time' => "{$date} 09:00:00",
            'clock_out_time' => "{$date} 18:00:00",
        ]);

        // 3. 日跨ぎデータで修正申請を作成
        $applicationData = [
            'attendance_id' => $attendance->id, // ★ 修正: Attendance IDを紐づける
            'user_id' => $this->user->id,
            'checkin_date' => $date,
            'clock_in_time' => '22:00', // 出勤 (10/27 22:00)
            'clock_out_time' => '06:00', // 退勤 (翌日10/28 06:00に補正されるはず)
            'reason' => $reason,
            'break_times' => [
                // 休憩も日跨ぎが考慮される (10/28 02:00 - 10/28 03:00に補正されるはず)
                ['start_time' => '02:00', 'end_time' => '03:00'], 
            ],
            // 💡 修正: データベースのNULL制約を満たすため、計算後の値をテストデータに追加
            'work_time' => 420, 
            'break_total_time' => 60,
        ];

        // 2. 一般ユーザーとして認証し、申請をPOST (route()ヘルパーを使用)
        $response = $this->actingAs($this->user)->post(route('application.create'), $applicationData);
        $response->assertSessionHasNoErrors();


        // データベースにデータが正しく保存され、日跨ぎ補正されていることを確認
        $this->assertDatabaseHas('applications', [
            'user_id' => $this->user->id,
            'reason' => $reason,
            'pending' => true, 
        ]);
        
        // 4. 作成された申請レコードを取得 (reasonで確実に特定)
        $application = Application::where('user_id', $this->user->id)
                                 ->where('reason', $reason)
                                 ->first();

        $this->assertNotNull($application, 'テストの前提: 申請レコードが作成されていません。');
        // pendingがtrue (または1) であることを確認
        $this->assertTrue((bool)$application->pending, 'テストの前提: 作成された申請が承認待ち(pending=true)で保存されていません。');
        
        // 5. 管理者ユーザーとして認証し、承認待ち一覧にアクセス (route()ヘルパーを使用)
        // ★修正: pendingパラメータをブーリアンではなく文字列の 'true' で渡す
        $response = $this->actingAs($this->admin)->get(route('apply.list', ['pending' => 'true']));
        
        // 6. レスポンスの確認
        $response->assertOk();
        
        // 7. ビューに渡されたデータ（$applications）に、作成した申請が含まれていることを確認
        $applicationsInView = $response->viewData('applications');
        
        // 管理者側のビューデータはIDを含んでいる前提で確認
        $this->assertTrue(
            $applicationsInView->pluck('id')->contains($application->id),
            '管理者一覧に、ユーザーが送信した承認待ちの申請が含まれていません。'
        );
        
        // 8. 承認済み申請は含まれていないことを確認するために、承認済みレコードを作成
        $approvedAttendance = Attendance::factory()->create([
            'user_id' => $this->user->id, 
            'checkin_date' => '2025-10-26'
        ]);
        $approvedApplication = Application::create([
            'attendance_id' => $approvedAttendance->id, // ★ 修正: Attendance IDを紐づける
            'user_id' => $this->user->id,
            'checkin_date' => '2025-10-26',
            'clock_in_time' => '2025-10-26 09:00:00',
            'clock_out_time' => '2025-10-26 18:00:00',
            'reason' => '承認済みダミー',
            'pending' => false // booleanを使用するように修正
        ]);

        // route()ヘルパーを使用し、クエリパラメータを配列で渡す
        // ★修正: pendingパラメータをブーリアンではなく文字列の 'true' で渡す
        $responsePendingList = $this->actingAs($this->admin)->get(route('apply.list', ['pending' => 'true']));
        $applicationsInViewPending = $responsePendingList->viewData('applications');
        
        // 承認待ちリストに承認済みのIDが含まれていないことを確認
        $this->assertFalse(
            $applicationsInViewPending->pluck('id')->contains($approvedApplication->id),
            '管理者視点の承認待ちリストに、承認済みの申請が含まれています。'
        );
    }
    
    /**
     * 一般ユーザーが承認待ちリストにアクセスしたとき、自分の承認待ち申請のみが表示されることを確認する。
     */
    public function test_employee_sees_only_their_pending_applications()
    {
        // 別の一般ユーザー（他人）を作成
        $otherUser = User::factory()->create(['role' => 'employee']);
        
        // データベースに保存される生の形式 (Y-m-d)
        $date1 = '2025-11-01';
        $date2 = '2025-11-02';
        $date3 = '2025-11-03';

        // 申請に必要なAttendanceレコードを作成し、IDを紐づける
        $attendance1 = Attendance::factory()->create(['user_id' => $this->user->id, 'checkin_date' => $date1]);
        $attendance2 = Attendance::factory()->create(['user_id' => $this->user->id, 'checkin_date' => $date2]);
        $attendance3 = Attendance::factory()->create(['user_id' => $otherUser->id, 'checkin_date' => $date3]);
        
        // 1. 認証待ち（自分の申請）
        $myPendingApp = Application::create([
            'attendance_id' => $attendance1->id, // ★ 修正: Attendance IDを紐づける
            'user_id' => $this->user->id,
            'checkin_date' => $date1,
            'clock_in_time' => "{$date1} 10:00:00",
            'clock_out_time' => "{$date1} 19:00:00",
            'reason' => '自分の承認待ち申請',
            'pending' => true, // booleanを使用するように修正
        ]);

        // 2. 承認済み（自分の申請） - リストに含まれないはず
        $myApprovedApp = Application::create([
            'attendance_id' => $attendance2->id, // ★ 修正: Attendance IDを紐づける
            'user_id' => $this->user->id,
            'checkin_date' => $date2,
            'clock_in_time' => "{$date2} 09:00:00",
            'clock_out_time' => "{$date2} 18:00:00",
            'reason' => '自分の承認済み申請',
            'pending' => false, // booleanを使用するように修正
        ]);
        
        // 3. 他人の申請（承認待ち） - リストに含まれないはず
        $otherPendingApp = Application::create([
            'attendance_id' => $attendance3->id, // ★ 修正: Attendance IDを紐づける
            'user_id' => $otherUser->id,
            'checkin_date' => $date3,
            'clock_in_time' => "{$date3} 11:00:00",
            'clock_out_time' => "{$date3} 20:00:00",
            'reason' => '他人の承認待ち申請',
            'pending' => true, // booleanを使用するように修正
        ]);

        // 一般ユーザーとして認証し、承認待ち一覧（?pending=true）にアクセス (route()ヘルパーを使用)
        // ★修正: pendingパラメータをブーリアンではなく文字列の 'true' で渡す
        $response = $this->actingAs($this->user)->get(route('apply.list', ['pending' => 'true']));

        $response->assertOk();
        
        $applicationsInView = $response->viewData('applications');
        
        // A. 自分の承認待ち申請が含まれていることを確認
        $this->assertTrue(
            $applicationsInView->pluck('id')->contains($myPendingApp->id),
            '一般ユーザーの承認待ちリストに、自分の承認待ち申請が含まれていません。'
        );

        // B. 自分の承認済み申請が含まれていないことを確認
        $this->assertFalse(
            $applicationsInView->pluck('id')->contains($myApprovedApp->id),
            '一般ユーザーの承認待ちリストに、自分の承認済み申請が含まれています。'
        );
        
        // C. 他人の承認待ち申請が含まれていないことを確認
        $this->assertFalse(
            $applicationsInView->pluck('id')->contains($otherPendingApp->id),
            '一般ユーザーの承認待ちリストに、他人の申請が含まれていません。'
        );
        
        // D. 取得された件数が自分の承認待ち申請（1件）のみであることを確認
        $this->assertCount(
            1, 
            $applicationsInView, 
            '一般ユーザーの承認待ちリストに、予期せぬ件数の申請が含まれています。'
        );
    }
    
    /**
     * 一般ユーザーが承認済みリストにアクセスしたとき、自分の承認済み申請のみが表示されることを確認する。
     */
    public function test_employee_sees_only_their_approved_applications()
    {
        // 別の一般ユーザー（他人）を作成
        $otherUser = User::factory()->create(['role' => 'employee']);
        
        $date1 = '2025-11-01';
        $date2 = '2025-11-02';
        $date3 = '2025-11-03';

        // 申請に必要なAttendanceレコードを作成し、IDを紐づける
        $attendance1 = Attendance::factory()->create(['user_id' => $this->user->id, 'checkin_date' => $date1]);
        $attendance2 = Attendance::factory()->create(['user_id' => $this->user->id, 'checkin_date' => $date2]);
        $attendance3 = Attendance::factory()->create(['user_id' => $otherUser->id, 'checkin_date' => $date3]);
        
        // 1. 承認済み（自分の申請） - 期待されるデータ
        $myApprovedApp = Application::create([
            'attendance_id' => $attendance2->id, // ★ 修正: Attendance IDを紐づける
            'user_id' => $this->user->id,
            'checkin_date' => $date2,
            'clock_in_time' => "{$date2} 09:00:00",
            'clock_out_time' => "{$date2} 18:00:00",
            'reason' => '自分の承認済み申請',
            'pending' => false, // booleanを使用するように修正
        ]);
        
        // 2. 認証待ち（自分の申請） - リストに含まれないはずのデータ
        $myPendingApp = Application::create([
            'attendance_id' => $attendance1->id, // ★ 修正: Attendance IDを紐づける
            'user_id' => $this->user->id,
            'checkin_date' => $date1,
            'clock_in_time' => "{$date1} 10:00:00",
            'clock_out_time' => "{$date1} 19:00:00",
            'reason' => '自分の承認待ち申請',
            'pending' => true, // booleanを使用するように修正
        ]);

        // 3. 他人の申請（承認済み） - リストに含まれないはずのデータ
        $otherApprovedApp = Application::create([
            'attendance_id' => $attendance3->id, // ★ 修正: Attendance IDを紐づける
            'user_id' => $otherUser->id,
            'checkin_date' => $date3,
            'clock_in_time' => "{$date3} 11:00:00",
            'clock_out_time' => "{$date3} 20:00:00",
            'reason' => '他人の承認済み申請',
            'pending' => false, // booleanを使用するように修正
        ]);

        // 一般ユーザーとして認証し、承認済み一覧（?pending=false）にアクセス (route()ヘルパーを使用)
        // ★修正: pendingパラメータをブーリアンではなく文字列の 'false' で渡す
        $response = $this->actingAs($this->user)->get(route('apply.list', ['pending' => 'false']));

        $response->assertOk();
        
        $applicationsInView = $response->viewData('applications');
        
        // A. 自分の承認済み申請が含まれていることを確認 (IDで検証)
        $this->assertTrue(
            $applicationsInView->pluck('id')->contains($myApprovedApp->id),
            '一般ユーザーの承認済みリストに、自分の承認済み申請が含まれていません。'
        );

        // B. 自分の承認待ち申請が含まれていないことを確認 (IDで検証)
        $this->assertFalse(
            $applicationsInView->pluck('id')->contains($myPendingApp->id),
            '一般ユーザーの承認済みリストに、自分の承認待ち申請が含まれています。'
        );
        
        // C. 他人の承認済み申請が含まれていないことを確認 (IDで検証)
        $this->assertFalse(
            $applicationsInView->pluck('id')->contains($otherApprovedApp->id),
            '一般ユーザーの承認済みリストに、他人の承認済み申請が含まれています。'
        );
        
        // D. 取得された件数が自分の承認済み申請（1件）のみであることを確認
        $this->assertCount(
            1, 
            $applicationsInView, 
            '一般ユーザーの承認済みリストに、予期せぬ件数の申請が含まれています。'
        );
    }

    // ====================================================================
    // 勤怠詳細ページ表示ロジックテスト
    // ====================================================================

    /**
     * 詳細ページへの遷移、勤怠・休憩データのフォーム初期値表示、
     * および申請ステータスに基づいて修正ボタンとメッセージが正しく表示されることをテストします。
     *
     * @return void
     */
    public function test_attendance_detail_page_displays_data_and_correct_buttons_based_on_status(): void
    {
        // ----------------------------------------------------
        // 共通設定: 勤怠データと詳細ページURLの準備
        // ----------------------------------------------------
        $targetDate = Carbon::create(2025, 10, 10);
        
        // 元の勤怠データ
        $originalCheckIn = '09:00';
        $originalCheckOut = '18:00';
        
        // 休憩データ
        $expectedBreakTimesArray = [
            ['start' => '12:00:00', 'end' => '13:00:00'], // 休憩1
            ['start' => '15:00:00', 'end' => '15:15:00'], // 休憩2
        ];
        $expectedBreakMinutes = 75; 
        $expectedWorkMinutes = 465; 

        // 勤怠データを作成（ベースデータとして使用）
        $attendanceBase = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate->format('Y-m-d'),
            // 元のデータは 09:00 / 18:00
            'clock_in_time' => "{$targetDate->format('Y-m-d')} {$originalCheckIn}:00", 
            'clock_out_time' => "{$targetDate->format('Y-m-d')} {$originalCheckOut}:00",
            'break_time' => json_encode($expectedBreakTimesArray), 
            'break_total_time' => $expectedBreakMinutes,
            'work_time' => $expectedWorkMinutes,
        ]);
        
        // ★★★ route()ヘルパーを使用して詳細ルートを生成（URLの直接構築を回避）★★★
        $detailRouteWithParams = route('user.attendance.detail.index', [
            'id' => $attendanceBase->id, 
            'date' => $targetDate->toDateString()
        ]);
        $updateButtonHtml = '<button type="submit" class="button update-button">修正</button>';

        // ----------------------------------------------------
        // Case 1: 申請データなし (データ表示と「修正」ボタンの表示検証)
        // ----------------------------------------------------
        $detailResponse = $this->actingAs($this->user)->get($detailRouteWithParams);

        // ルーティングと基本表示のアサート
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('勤怠詳細・修正申請', 'h2');
        
        // 日付検証 (表示形式のパターンマッチングは難しいので、ここでは簡易的なアサートに留める)
        $detailResponse->assertSee($targetDate->year); 
        $detailResponse->assertSee($targetDate->month . '月' . $targetDate->day . '日', false); 

        // ★★★ フォームへのデータ初期値セットを検証 ★★★
        $detailResponse->assertSee('value="' . $originalCheckIn . '"', false);      
        $detailResponse->assertSee('value="' . $originalCheckOut . '"', false);     
        $detailResponse->assertSee('value="12:00"', false); // 休憩1 開始
        $detailResponse->assertSee('value="13:00"', false); // 休憩1 終了
        $detailResponse->assertSee('value="15:00"', false); // 休憩2 開始
        $detailResponse->assertSee('value="15:15"', false); // 休憩2 終了

        // ★★★ ボタン表示ロジックの検証（Case 1: 申請データなし => 修正ボタンが表示される）★★★
        $detailResponse->assertSee($updateButtonHtml, false); 
        $detailResponse->assertDontSee('＊承認待ちのため修正はできません。');
        $detailResponse->assertDontSee('＊この日は一度承認されたので修正できません。');
        
        // ----------------------------------------------------
        // Case 2: 承認待ちの申請データが存在する場合 
        // ----------------------------------------------------
        $targetDate2 = $targetDate->copy()->addDay(); // Carbonオブジェクトをコピーして日付を進める
        // 新しい勤怠データを作成
        $attendance2 = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate2->format('Y-m-d'),
            'clock_in_time' => "{$targetDate2->format('Y-m-d')} 09:00:00", // 元は 09:00
            'clock_out_time' => "{$targetDate2->format('Y-m-d')} 18:00:00",
            'work_time' => 540,
            'break_total_time' => 60,
        ]);
        
        $pendingCheckIn = '08:00'; // 申請により 08:00 に修正
        // 承認待ちの申請データを作成
        Application::create([
            'attendance_id' => $attendance2->id, 
            'user_id' => $this->user->id,
            'pending' => true, // booleanを使用するように修正
            'checkin_date' => $attendance2->checkin_date,
            'clock_in_time' => "{$attendance2->checkin_date} {$pendingCheckIn}:00",
            
            // ★修正箇所: NOT NULL制約を回避するため clock_out_time を追加
            'clock_out_time' => "{$attendance2->checkin_date} 17:00:00", 
            
            'reason' => 'Pending test reason', 
            // 💡 修正: データベースのNULL制約を満たすため、計算後の値を追加
            'work_time' => 540,
            'break_total_time' => 60,
        ]);
        
        // ★★★ route()ヘルパーを使用して詳細ルートを生成（URLの直接構築を回避）★★★
        $detailRoute2WithParams = route('user.attendance.detail.index', [
            'id' => $attendance2->id, 
            'date' => $attendance2->checkin_date
        ]);
        $detailResponse2 = $this->actingAs($this->user)->get($detailRoute2WithParams);

        // ★★★ フォームへのデータ初期値セットを検証 (申請データ 08:00 が優先されること) ★★★
        $detailResponse2->assertStatus(200);
        $detailResponse2->assertSee('value="' . $pendingCheckIn . '"', false); // 申請値の 08:00 が表示される
        $detailResponse2->assertDontSee('value="' . $originalCheckIn . '"', false); // 元の値 09:00 は表示されない
        
        // ★★★ ボタン表示ロジックの検証（Case 2: 承認待ち => 修正ボタンが非表示になり、メッセージが表示される）★★★
        $detailResponse2->assertDontSee('修正</button>', false); 
        $detailResponse2->assertSee('＊承認待ちのため修正はできません。');
        $detailResponse2->assertDontSee('＊この日は一度承認されたので修正できません。');
        
        // ----------------------------------------------------
        // Case 3: 承認済みの申請データが存在する場合 
        // ----------------------------------------------------
        $targetDate3 = $targetDate->copy()->addDays(2); // Carbonオブジェクトをコピーして日付を進める
        // 新しい勤怠データを作成
        $attendance3 = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate3->format('Y-m-d'),
            'clock_in_time' => "{$targetDate3->format('Y-m-d')} 09:00:00", // 元は 09:00
            'clock_out_time' => "{$targetDate3->format('Y-m-d')} 18:00:00",
            'work_time' => 540,
            'break_total_time' => 60,
        ]);
        
        $approvedCheckIn = '07:00'; // 申請により 07:00 に修正
        // 承認済みの申請データを作成
        Application::create([
            'attendance_id' => $attendance3->id, 
            'user_id' => $this->user->id,
            'pending' => false, // booleanを使用するように修正
            'checkin_date' => $attendance3->checkin_date,
            'clock_in_time' => "{$attendance3->checkin_date} {$approvedCheckIn}:00",
            
            // ★修正箇所: NOT NULL制約を回避するため clock_out_time を追加
            'clock_out_time' => "{$attendance3->checkin_date} 16:00:00", 
            
            'reason' => 'Approved test reason', 
            // 💡 修正: データベースのNULL制約を満たすため、計算後の値を追加
            'work_time' => 600,
            'break_total_time' => 60,
        ]);

        // ★★★ route()ヘルパーを使用して詳細ルートを生成（URLの直接構築を回避）★★★
        $detailRoute3WithParams = route('user.attendance.detail.index', [
            'id' => $attendance3->id, 
            'date' => $attendance3->checkin_date
        ]);
        $detailResponse3 = $this->actingAs($this->user)->get($detailRoute3WithParams);
        
        // ★★★ フォームへのデータ初期値セットを検証 (申請データ 07:00 が優先されること) ★★★
        $detailResponse3->assertStatus(200);
        $detailResponse3->assertSee('value="' . $approvedCheckIn . '"', false); // 申請値の 07:00 が表示される
        $detailResponse3->assertDontSee('value="' . $originalCheckIn . '"', false); // 元の値 09:00 は表示されない

        // ★★★ ボタン表示ロジックの検証（Case 3: 承認済み => 修正ボタンが非表示になり、メッセージが表示される）★★★
        $detailResponse3->assertDontSee('修正</button>', false);
        $detailResponse3->assertDontSee('＊承認待ちのため修正はできません。');
        $detailResponse3->assertSee('＊この日は一度承認されたので修正できません。');
    }
}