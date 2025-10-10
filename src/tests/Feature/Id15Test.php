<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use App\Models\User;
use App\Models\Application;
use Carbon\Carbon;


// ID15 勤怠情報修正（管理者）機能のテスト
class Id15Test extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $staffUser1;
    protected $staffUser2;
    protected $pendingApplication1;
    protected $pendingApplication2;
    protected $approvedApplication1;
    protected $approvedApplication2;


    protected function setUp(): void
    {
        parent::setUp();

        // 管理者ユーザー (ID: 100)
        $this->adminUser = User::factory()->create(['role' => 'admin', 'name' => '管理者A', 'id' => 100]);

        // スタッフユーザー
        $this->staffUser1 = User::factory()->create(['name' => 'スタッフA', 'role' => 'staff']);
        $this->staffUser2 = User::factory()->create(['name' => 'スタッフB', 'role' => 'staff']);

        $targetDate = '2025-09-20'; // 修正対象の勤怠日付 (過去) -> checkin_date
        $targetDate2 = '2025-09-21'; // 別の勤怠日付
        $requestDate = '2025-09-30 14:30:00'; // 申請を行った日時 (created_at)

        // 承認待ちの申請 1 (pending = true) - スタッフA、テスト承認対象
        $this->pendingApplication1 = Application::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $targetDate, // 対象日時
            'clock_in_time' => '09:10:00',
            'clock_out_time' => '18:10:00',
            'reason' => '電車遅延による打刻修正（承認待ち 1）',
            'break_time' => json_encode([['start' => '12:00:00', 'end' => '13:00:00']]),
            'pending' => true,
            'created_at' => $requestDate,
            'updated_at' => $requestDate,
        ]);

        // 承認待ちの申請 2 (pending = true) - スタッフB
        $this->pendingApplication2 = Application::factory()->create([
            'user_id' => $this->staffUser2->id,
            'checkin_date' => $targetDate2, // 別の対象日時
            'clock_in_time' => '09:00:00',
            'clock_out_time' => '18:00:00',
            'reason' => '急用による早退（承認待ち 2）',
            'break_time' => json_encode([['start' => '12:00:00', 'end' => '13:00:00']]),
            'pending' => true,
            'created_at' => $requestDate,
            'updated_at' => $requestDate,
        ]);


        // 承認済みの申請 1 (pending = false) - スタッフB
        $this->approvedApplication1 = Application::factory()->create([
            'user_id' => $this->staffUser2->id,
            'checkin_date' => $targetDate, // 対象日時
            'clock_in_time' => '09:05:00',
            'clock_out_time' => '18:05:00',
            'reason' => '軽微な修正（承認済み 1）',
            // 休憩2回
            'break_time' => json_encode([
                ['start' => '12:00:00', 'end' => '12:30:00'],
                ['start' => '15:00:00', 'end' => '15:15:00'],
            ]),
            'pending' => false,
            'created_at' => $requestDate,
            'updated_at' => $requestDate,
        ]);

        // 承認済みの申請 2 (pending = false) - スタッフA
        $this->approvedApplication2 = Application::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $targetDate2, // 別の対象日時
            'clock_in_time' => '08:50:00',
            'clock_out_time' => '17:50:00',
            'reason' => 'PC起動遅延（承認済み 2）',
            'break_time' => json_encode([['start' => '12:00:00', 'end' => '13:00:00']]),
            'pending' => false,
            'created_at' => $requestDate,
            'updated_at' => $requestDate,
        ]);
    }

    // ID15-1 承認待ちタブ (デフォルト) の表示とフィルタリングを検証します。
    public function test_admin_apply_list_shows_pending_applications_by_default()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('apply.list'));

        $response->assertStatus(200);

        $response->assertSee('承認待ち');

        // 承認待ちの申請 1, 2 が表示されていること
        $response->assertSee($this->pendingApplication1->user->name);
        $response->assertSee('電車遅延による打刻修正（承認待ち 1）');
        $response->assertSee($this->pendingApplication2->user->name);
        $response->assertSee('急用による早退（承認待ち 2）');

        // 対象日時 (checkin_date: 2025/09/20) と 申請日時 (created_at: 2025/09/30) の両方がHTMLに含まれていることを確認
        $response->assertSee('2025/09/20'); // 対象日時 1
        $response->assertSee('2025/09/21'); // 対象日時 2
        $response->assertSee('2025/09/30'); // 申請日時

        // 承認済みの申請は表示されていないこと
        $response->assertDontSee($this->approvedApplication1->reason);
        $response->assertDontSee($this->approvedApplication2->reason);
    }

    // ID15-2 承認済みタブが押されたときの表示とフィルタリングを検証します。
    public function test_admin_apply_list_shows_approved_applications()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('apply.list', ['pending' => 'false']));

        $response->assertStatus(200);

        $response->assertSee('承認済み');

        // 承認済みの申請 1, 2 が表示されていること
        $response->assertSee($this->approvedApplication1->user->name);
        $response->assertSee('軽微な修正（承認済み 1）');
        $response->assertSee($this->approvedApplication2->user->name);
        $response->assertSee('PC起動遅延（承認済み 2）');

        // 対象日時 (checkin_date: 2025/09/20, 2025/09/21) と 申請日時 (created_at: 2025/09/30) の両方がHTMLに含まれていることを確認
        $response->assertSee('2025/09/20'); // 対象日時 1
        $response->assertSee('2025/09/21'); // 対象日時 2
        $response->assertSee('2025/09/30'); // 申請日時

        // 承認待ちの申請は表示されていないこと
        $response->assertDontSee($this->pendingApplication1->reason);
        $response->assertDontSee($this->pendingApplication2->reason);
    }

    // ID15-1,3 承認待ち申請の詳細ページ表示と「承認」ボタンの有無を検証します。
    public function test_admin_apply_judgement_index_for_pending_application()
    {
        // admin.apply.judgement.index (管理者申請詳細) も adminUserでアクセス可能と仮定
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.apply.judgement.index', ['attendance_correct_request_id' => $this->pendingApplication1->id]));

        $response->assertStatus(200);

        // ページのタイトル、名前を確認
        $response->assertSee('勤怠詳細');
        $response->assertSee('スタッフA');

        // 日付の検証を、全角スペースに依存しない形で分割して検証
        $response->assertSee('2025年');
        $response->assertSee('9月20日');

        // 申請内容の確認（出勤・退勤 -> 申請理由 の主要な順序を確認）
        $response->assertSeeInOrder(['09:10', '18:10', '電車遅延による打刻修正（承認待ち 1）']);

        // 承認待ちのため、「承 認」ボタンが表示されていること (スペースを含めたテキストで検証)
        $response->assertSee('承 認</button>', false);

        // フォームアクションとIDを確認
        $response->assertSee('<form action="' . route('admin.apply.attendance.approve') . '" method="post">', false);
        $response->assertSee('<input type="hidden" name="id" value="' . $this->pendingApplication1->id . '">', false);
    }

    // ID15-2,3 承認済み申請の詳細ページ表示と「承認済み」ボタンの有無を検証します。
    public function test_admin_apply_judgement_index_for_approved_application()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.apply.judgement.index', ['attendance_correct_request_id' => $this->approvedApplication1->id]));

        $response->assertStatus(200);

        // ページのタイトル、名前を確認
        $response->assertSee('勤怠詳細');
        $response->assertSee('スタッフB');

        // 日付の検証を、全角スペースに依存しない形で分割して検証
        $response->assertSee('2025年');
        $response->assertSee('9月20日');

        // 申請内容の確認（出勤・退勤 -> 申請理由 の主要な順序を確認）
        $response->assertSeeInOrder(['09:05', '18:05', '軽微な修正（承認済み 1）']);

        // 承認済みのため、「承認済み」ボタン（disabled）が表示され、「承認」ボタンは表示されていないこと
        $response->assertDontSee('承 認</button>', false);

        // HTMLログから、承認済みボタンのHTMLを確認し、アサーションを調整
        $response->assertSee('承 認 済 み</button>', false);
        $response->assertSee('disabled', false);
    }

    // ID15-4 管理者が承認待ちの申請を承認できることを検証し、承認後に申請が承認待ち一覧から消えることを確認します。
    public function test_admin_can_approve_pending_application()
    {
        // 承認アクションを実行 (POST /admin/apply/attendance/approve)
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.apply.attendance.approve'), [
                'id' => $this->pendingApplication1->id,
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('apply.list'));

        // 申請レコードが承認済みになっていることをassertDatabaseHasで直接検証する
        $this->assertDatabaseHas('applications', [
            'id' => $this->pendingApplication1->id,
            'pending' => false, // 承認済みなのでfalseになっている。
        ]);

        $today = Carbon::now()->format('Y-m-d');
        $expectedClockIn = $today . ' 09:10:00';
        $expectedClockOut = $today . ' 18:10:00';

        // 勤怠レコードが作成/更新され、申請内容が反映されていることを確認
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->staffUser1->id,
            'checkin_date' => '2025-09-20',
            'clock_in_time' => $expectedClockIn, // 実行日時の日付を付加した形式で期待
            'clock_out_time' => $expectedClockOut, // 実行日時の日付を付加した形式で期待
        ]);

        // 承認後、承認待ち一覧 (pending=true) から消えていることを検証
        $responseAfterApproval = $this->actingAs($this->adminUser)
            ->get(route('apply.list', ['pending' => 'true']));

        $responseAfterApproval->assertStatus(200);

        // 承認した申請1の理由（ユニークな文字列）は一覧から消えていること
        $approvedRecord = $this->pendingApplication1->fresh(); // 最新のレコードを取得して理由を確認
        $responseAfterApproval->assertDontSee($approvedRecord->reason);
        // 承認していない申請2（スタッフB）はまだ残っていること
        $responseAfterApproval->assertSee($this->pendingApplication2->user->name);
        $responseAfterApproval->assertSee($this->pendingApplication2->reason);
    }
}