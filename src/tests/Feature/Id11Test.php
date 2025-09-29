<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Application;
use App\Models\Attendance;
use Carbon\Carbon;

/**
 * 勤怠修正申請のバリデーション (ID11) と
 * 申請一覧表示・日跨ぎ補正ロジックの連携 (ID12) を統合してテストします。
 */
class Id11Test extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $postRoute;
    protected $listRoute;
    protected $validData;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 1. ユーザー作成
        $this->user = User::factory()->create(['role' => 'employee']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        // 2. ルート定義
        try {
            $this->postRoute = route('application.create'); // /attendance/update
            $this->listRoute = route('apply.list'); // /stamp_correction_request/list
        } catch (\InvalidArgumentException $e) {
            // ルートが定義されていない環境に対応
            $this->postRoute = '/application/create'; 
            $this->listRoute = '/stamp_correction_request/list';
        }
        
        // 3. 成功するリクエストのベースデータ (ID11 Valid Data)
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
        ];
    }

    // ====================================================================
    // ID11: 勤怠修正申請のバリデーションテスト
    // ====================================================================

    /**
     * 【検証 1】必須フィールド（出勤時刻、退勤時刻、備考）の欠落をチェック。
     * メッセージ: required, reason.required
     */
    public function test_required_fields_check()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '';
        $invalidData['clock_out_time'] = '';
        $invalidData['reason'] = '';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'clock_in_time' => '出勤時刻を入力してください。',
            'clock_out_time' => '退勤時刻を入力してください。',
            'reason' => '備考を記入してください。',
        ]);
    }

    /**
     * 【検証 2】出勤時刻が退勤時刻より後になっている順序エラーをチェック。
     * ルール: clock_in_time.before:clock_out_time
     * メッセージ: 出勤時刻が不適切な値です。
     */
    public function test_clock_in_after_clock_out_fails()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '19:00'; // 18:00より後
        $invalidData['clock_out_time'] = '18:00';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'clock_in_time' => '出勤時刻が不適切な値です。',
        ]);
    }

    /**
     * 【検証 3】退勤時刻が出勤時刻より前になっている順序エラーをチェック。
     * ルール: clock_out_time.after:clock_in_time
     * メッセージ: 退勤時間が不適切な値です。
     */
    public function test_clock_out_before_clock_in_fails()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '09:00';
        $invalidData['clock_out_time'] = '08:00'; // 09:00より前

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

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

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

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

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

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

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

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

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

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

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'break_times.0.end_time' => '休憩時間もしくは退勤時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 9】全ての時刻が正しい場合（成功ケース）。
     */
    public function test_valid_data_passes_validation()
    {
        $response = $this->actingAs($this->user)->post($this->postRoute, $this->validData);

        $response->assertSessionHasNoErrors();
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

        // 3. 日跨ぎデータで修正申請を作成 (application_createのロジックテストも兼ねる)
        $applicationData = [
            'attendance_id' => null,
            'checkin_date' => $date,
            'clock_in_time' => '22:00', // 出勤 (10/27 22:00)
            'clock_out_time' => '06:00', // 退勤 (翌日10/28 06:00に補正されるはず)
            'reason' => '夜勤明けのため、退勤時間が翌日になっています。',
            'break_times' => [
                // 休憩も日跨ぎが考慮される (10/28 02:00 - 10/28 03:00に補正されるはず)
                ['start_time' => '02:00', 'end_time' => '03:00'], 
            ],
        ];

        // 2. 一般ユーザーとして認証し、申請をPOST (application_createが実行される)
        $response = $this->actingAs($this->user)->post($this->postRoute, $applicationData);
        $response->assertSessionHasNoErrors();


        // データベースにデータが正しく保存され、日跨ぎ補正されていることを確認
        // Controller側の処理が日跨ぎ補正を行っている前提
        $expectedClockOut = Carbon::parse($date . ' 06:00')->addDay()->toDateTimeString();
        $this->assertDatabaseHas('applications', [
            'user_id' => $this->user->id,
            'pending' => true, // 承認待ちで保存されていることを確認
            'clock_out_time' => $expectedClockOut, // 翌日への補正を確認
        ]);
        
        // 作成された申請レコードを取得
        $application = Application::where('user_id', $this->user->id)->first();
        
        // 5. 管理者ユーザーとして認証し、承認待ち一覧にアクセス
        $response = $this->actingAs($this->admin)->get($this->listRoute . '?pending=true');
        
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
        $approvedApplication = Application::create([
            'user_id' => $this->user->id,
            'checkin_date' => '2025-10-26',
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'reason' => '承認済みダミー',
            'pending' => false // 承認済みのレコード
        ]);


        $responsePendingList = $this->actingAs($this->admin)->get($this->listRoute . '?pending=true');
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
        
        // 1. 認証待ち（自分の申請）
        $myPendingApp = Application::create([
            'user_id' => $this->user->id,
            'checkin_date' => $date1,
            'clock_in_time' => '10:00:00',
            'clock_out_time' => '19:00:00',
            'reason' => '自分の承認待ち申請',
            'pending' => true,
        ]);

        // 2. 承認済み（自分の申請） - リストに含まれないはず
        $myApprovedApp = Application::create([
            'user_id' => $this->user->id,
            'checkin_date' => $date2,
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'reason' => '自分の承認済み申請',
            'pending' => false,
        ]);
        
        // 3. 他人の申請（承認待ち） - リストに含まれないはず
        $otherPendingApp = Application::create([
            'user_id' => $otherUser->id,
            'checkin_date' => $date3,
            'clock_in_time' => '11:00:00',
            'clock_out_time' => '20:00:00',
            'reason' => '他人の承認待ち申請',
            'pending' => true,
        ]);

        // 一般ユーザーとして認証し、承認待ち一覧（?pending=true）にアクセス
        $response = $this->actingAs($this->user)->get($this->listRoute . '?pending=true');

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
            '一般ユーザーの承認待ちリストに、他人の申請が含まれています。'
        );
        
        // D. 取得された件数が自分の承認待ち申請（1件）のみであることを確認
        $this->assertCount(
            1, 
            $applicationsInView, 
            '一般ユーザーの承認待ちリストに、予期せぬ件数の申請が含まれています。'
        );
    }
    
    /**
     * 【新規テスト】一般ユーザーが承認済みリストにアクセスしたとき、自分の承認済み申請のみが表示されることを確認する。
     */
    public function test_employee_sees_only_their_approved_applications()
    {
        // 別の一般ユーザー（他人）を作成
        $otherUser = User::factory()->create(['role' => 'employee']);
        
        // 1. 承認済み（自分の申請） - 期待されるデータ
        $myApprovedApp = Application::create([
            'user_id' => $this->user->id,
            'checkin_date' => '2025-11-02',
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'reason' => '自分の承認済み申請',
            'pending' => false, // 承認済み
        ]);
        
        // 2. 認証待ち（自分の申請） - リストに含まれないはずのデータ
        $myPendingApp = Application::create([
            'user_id' => $this->user->id,
            'checkin_date' => '2025-11-01',
            'clock_in_time' => '10:00:00',
            'clock_out_time' => '19:00:00',
            'reason' => '自分の承認待ち申請',
            'pending' => true, // 承認待ち
        ]);

        // 3. 他人の申請（承認済み） - リストに含まれないはずのデータ
        $otherApprovedApp = Application::create([
            'user_id' => $otherUser->id,
            'checkin_date' => '2025-11-03',
            'clock_in_time' => '11:00:00',
            'clock_out_time' => '20:00:00',
            'reason' => '他人の承認済み申請',
            'pending' => false, // 承認済み
        ]);

        // 一般ユーザーとして認証し、承認済み一覧（?pending=false）にアクセス
        $response = $this->actingAs($this->user)->get($this->listRoute . '?pending=false');

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
            'clock_in_time' => "{$originalCheckIn}:00", 
            'clock_out_time' => "{$originalCheckOut}:00",
            'break_time' => json_encode($expectedBreakTimesArray), 
            'break_total_time' => $expectedBreakMinutes,
            'work_time' => $expectedWorkMinutes,
        ]);
        
        $expectedPath = "/attendance/detail/{$attendanceBase->id}?date={$targetDate->toDateString()}";
        $updateButtonHtml = '<button type="submit" class="button update-button">修正</button>';

        // ----------------------------------------------------
        // Case 1: 申請データなし (データ表示と「修正」ボタンの表示検証)
        // 詳細ページには元の勤怠データが表示されるべき（勤怠一覧からの遷移を想定）
        // ----------------------------------------------------
        $detailResponse = $this->actingAs($this->user)->get($expectedPath);

        // ルーティングと基本表示のアサート
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('勤怠詳細・修正申請', 'h2');
        $detailResponse->assertSee($targetDate->format('Y年m月d日'));

        // ★★★ フォームへのデータ初期値セットを検証 (元のデータ 09:00 / 18:00) ★★★
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
        // Case 2: 承認待ちの申請データが存在する場合 (★申請履歴の「承認待ち」詳細から移動してきた場合の検証★)
        // 詳細ページには申請データが表示されるべき
        // ----------------------------------------------------
        $targetDate2 = $targetDate->addDay();
        // 新しい勤怠データを作成
        $attendance2 = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate2->format('Y-m-d'),
            'clock_in_time' => '09:00:00', // 元は 09:00
        ]);
        
        $pendingCheckIn = '08:00'; // 申請により 08:00 に修正
        // 承認待ちの申請データを作成
        Application::create([
            'attendance_id' => $attendance2->id, 
            'user_id' => $this->user->id,
            'pending' => true, // 承認待ち
            'checkin_date' => $attendance2->checkin_date,
            'clock_in_time' => "{$pendingCheckIn}:00",
            'reason' => 'Pending test reason', 
        ]);
        $expectedPath2 = "/attendance/detail/{$attendance2->id}?date={$attendance2->checkin_date}";
        $detailResponse2 = $this->actingAs($this->user)->get($expectedPath2);

        // ★★★ フォームへのデータ初期値セットを検証 (申請データ 08:00 が優先されること) ★★★
        $detailResponse2->assertStatus(200);
        $detailResponse2->assertSee('value="' . $pendingCheckIn . '"', false); // 申請値の 08:00 が表示される
        $detailResponse2->assertDontSee('value="' . $originalCheckIn . '"', false); // 元の値 09:00 は表示されない
        
        // ★★★ ボタン表示ロジックの検証（Case 2: 承認待ち => 修正ボタンが非表示になり、メッセージが表示される）★★★
        $detailResponse2->assertDontSee('修正</button>', false); 
        $detailResponse2->assertSee('＊承認待ちのため修正はできません。');
        $detailResponse2->assertDontSee('＊この日は一度承認されたので修正できません。');
        
        // ----------------------------------------------------
        // Case 3: 承認済みの申請データが存在する場合 (★申請履歴の「承認済み」詳細から移動してきた場合の検証★)
        // 詳細ページには申請データが表示されるべき
        // ----------------------------------------------------
        $targetDate3 = $targetDate->addDay();
        // 新しい勤怠データを作成
        $attendance3 = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'checkin_date' => $targetDate3->format('Y-m-d'),
            'clock_in_time' => '09:00:00', // 元は 09:00
        ]);
        
        $approvedCheckIn = '07:00'; // 申請により 07:00 に修正
        // 承認済みの申請データを作成
        Application::create([
            'attendance_id' => $attendance3->id, 
            'user_id' => $this->user->id,
            'pending' => false, // 承認済み
            'checkin_date' => $attendance3->checkin_date,
            'clock_in_time' => "{$approvedCheckIn}:00",
            'reason' => 'Approved test reason', 
        ]);
        $expectedPath3 = "/attendance/detail/{$attendance3->id}?date={$attendance3->checkin_date}";
        $detailResponse3 = $this->actingAs($this->user)->get($expectedPath3);
        
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