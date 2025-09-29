<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Application;
use Carbon\Carbon;

use App\Models\Attendance; // Attendance Model を使用するため追加
// Undefined variable $errors エラー回避のため追加
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
/**
 * 管理者向けの申請一覧表示機能と、ユーザーからの申請作成処理の連携をテストします。
 * 特に、日跨ぎ補正後の申請が承認待ちリストに表示されることを検証します。
 */
class Id12Test extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $postRoute;
    protected $listRoute;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 1. 管理者ユーザーと一般ユーザーを作成
        $this->user = User::factory()->create(['role' => 'employee']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        // ルート定義
        try {
            $this->postRoute = route('application.create'); // /attendance/update
            $this->listRoute = route('apply.list'); // /stamp_correction_request/list
        } catch (\InvalidArgumentException $e) {
            $this->postRoute = '/application/create'; 
            $this->listRoute = '/stamp_correction_request/list';
        }
    }

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
            '一般ユーザーの承認済みリストに、他人の申請が含まれています。'
        );
        
        // D. 取得された件数が自分の承認済み申請（1件）のみであることを確認
        $this->assertCount(
            1, 
            $applicationsInView, 
            '一般ユーザーの承認済みリストに、予期せぬ件数の申請が含まれています。'
        );
    }

    /**
     * 承認ステータスに基づいて勤怠修正ボタンとメッセージが正しく表示されるかを確認する。
     * user_attendance_detail.blade.php の button-container ロジックの検証。
     */
    public function test_application_status_controls_view_buttons()
    {
        $testDate = '2025-12-01';

        // Undefined variable $errors を回避するため、空の ViewErrorBag インスタンスを渡す。
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag);
        
        // Viewに必要な共通ダミーデータ
        $commonViewData = [
            'user' => $this->user,
            'date' => $testDate,
            'formBreakTimes' => [],
            'errors' => $errors, 
        ];
        
        // 修正申請ボタンが表示されるための勤怠データをDBに作成し、IDを確実に持つようにする
        $realAttendance = Attendance::create([
            'user_id' => $this->user->id, 
            'checkin_date' => $testDate, 
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'reason' => null, // 備考をnullに設定し、Applicationのデータが漏れないようにする
        ]);
        
        // チェック対象のボタンHTMLの開始タグ
        $updateButtonHtml = '<button type="submit" class="button update-button">修正</button>';
        // チェック対象のボタンHTML（部分）
        $updateButtonTag = '<button type="submit" class="button update-button">';

        // =========================================================
        // Case 1: 申請データが存在しない場合 ($application = null)
        // =========================================================
        $viewData1 = array_merge($commonViewData, [
            'application' => null, // ここで明示的にnullを設定
            'attendance' => $realAttendance, // 勤怠データあり
            'primaryData' => $realAttendance, 
        ]);
        // actingAsを付与し、Authコンテキストを確実に渡す
        $response1 = $this->actingAs($this->user)->view('user_attendance_detail', $viewData1);
        
        // 期待: 修正ボタン全体が表示され、メッセージは非表示
        $response1->assertSee($updateButtonHtml, false); // ボタンの完全なHTMLでチェック
        $response1->assertDontSee('＊承認待ちのため修正はできません。');
        $response1->assertDontSee('＊この日は一度承認されたので修正できません。');
        
        // =========================================================
        // Case 2: 承認待ちの申請データが存在する場合 ($application->pending = true)
        // =========================================================
        // Applicationモデルをインスタンス化
        $pendingApp = new Application([
            'id' => 1, 
            'attendance_id' => $realAttendance->id, 
            'user_id' => $this->user->id,
            'pending' => true, // 承認待ち
            'checkin_date' => $testDate,
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'reason' => 'Pending test reason', 
        ]);
        $viewData2 = array_merge($commonViewData, [
            'application' => $pendingApp,
            'attendance' => $realAttendance, 
            'primaryData' => $pendingApp, // 申請データ優先
        ]);

        $response2 = $this->actingAs($this->user)->view('user_attendance_detail', $viewData2);

        // 期待: 承認待ちメッセージが表示され、修正ボタンは非表示
        // タイトルとの競合を避けるため、ボタンのHTMLタグが存在しないことをチェック
        $response2->assertDontSee($updateButtonTag, false); 
        $response2->assertSee('＊承認待ちのため修正はできません。');
        $response2->assertDontSee('＊この日は一度承認されたので修正できません。');
        
        // =========================================================
        // Case 3: 承認済みの申請データが存在する場合 ($application->pending = false)
        // =========================================================
        // Applicationモデルをインスタンス化
        $approvedApp = new Application([
            'id' => 2, 
            'attendance_id' => $realAttendance->id, 
            'user_id' => $this->user->id,
            'pending' => false, // 承認済み
            'checkin_date' => $testDate,
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'reason' => 'Approved test reason', 
        ]);
        $viewData3 = array_merge($commonViewData, [
            'application' => $approvedApp,
            'attendance' => $realAttendance, 
            'primaryData' => $approvedApp, // 申請データ優先
        ]);

        $response3 = $this->actingAs($this->user)->view('user_attendance_detail', $viewData3);
        
        // 期待: 承認済みメッセージが表示され、修正ボタンは非表示
        // タイトルとの競合を避けるため、ボタンのHTMLタグが存在しないことをチェック
        $response3->assertDontSee($updateButtonTag, false);
        $response3->assertDontSee('＊承認待ちのため修正はできません。');
        $response3->assertSee('＊この日は一度承認されたので修正できません。');
    }
}