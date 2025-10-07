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
    protected $pendingApplication;
    protected $approvedApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // ----------------------------------------------------
        // 1. テストデータの準備
        // ----------------------------------------------------

        // 管理者ユーザー (ID: 100)
        $this->adminUser = User::factory()->create(['role' => 'admin', 'name' => '管理者A', 'id' => 100]);

        // スタッフユーザー
        $this->staffUser1 = User::factory()->create(['name' => 'スタッフA', 'role' => 'staff']);
        $this->staffUser2 = User::factory()->create(['name' => 'スタッフB', 'role' => 'staff']);

        // 対象日時が申請日時より過去になるように設定
        $targetDate = '2025-09-20'; // 修正対象の勤怠日付 (過去) -> checkin_date
        $requestDate = '2025-09-30 14:30:00'; // 申請を行った日時 (created_at)

        // 承認待ちの申請 (pending = true)
        $this->pendingApplication = Application::factory()->create([
            'user_id' => $this->staffUser1->id,
            'checkin_date' => $targetDate, // 対象日時
            'clock_in_time' => '09:10:00',
            'clock_out_time' => '18:10:00',
            'reason' => '電車遅延による打刻修正（承認待ち）',
            'break_time' => json_encode([['start' => '12:00:00', 'end' => '13:00:00']]),
            'pending' => true,
            'created_at' => $requestDate, // 申請日時
            'updated_at' => $requestDate,
        ]);

        // 承認済みの申請 (pending = false)
        $this->approvedApplication = Application::factory()->create([
            'user_id' => $this->staffUser2->id,
            'checkin_date' => $targetDate, // 対象日時
            'clock_in_time' => '09:05:00',
            'clock_out_time' => '18:05:00',
            'reason' => '軽微な修正（承認済み）',
            // 休憩2回
            'break_time' => json_encode([
                ['start' => '12:00:00', 'end' => '12:30:00'],
                ['start' => '15:00:00', 'end' => '15:15:00'],
            ]),
            'pending' => false,
            'created_at' => $requestDate, // 申請日時
            'updated_at' => $requestDate,
        ]);
    }

    // ----------------------------------------------------
    // テストケース
    // ----------------------------------------------------

    /**
     * 承認待ちタブ (デフォルト) の表示とフィルタリングを検証します。
     */
    public function test_admin_apply_list_shows_pending_applications_by_default()
    {
        // apply.list (管理者申請一覧) は adminUserでアクセス可能と仮定
        $response = $this->actingAs($this->adminUser)
            ->get(route('apply.list'));

        $response->assertStatus(200);

        // アクティブなタブの状態を確認
        $response->assertSee('承認待ち');

        // 承認待ちの申請が表示されていること
        $response->assertSee($this->pendingApplication->user->name);
        $response->assertSee('電車遅延による打刻修正（承認待ち）');

        // 対象日時 (checkin_date: 2025/09/20) と 申請日時 (created_at: 2025/09/30) の両方が
        // HTMLに含まれていることを確認
        $response->assertSee('2025/09/20'); // 対象日時
        $response->assertSee('2025/09/30'); // 申請日時

        // 承認済みの申請は表示されていないこと
        $response->assertDontSee($this->approvedApplication->user->name);
    }

    /**
     * 承認済みタブが押されたときの表示とフィルタリングを検証します。
     */
    public function test_admin_apply_list_shows_approved_applications()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('apply.list', ['pending' => 'false']));

        $response->assertStatus(200);

        // アクティブなタブの状態を確認
        $response->assertSee('承認済み');

        // 承認済みの申請が表示されていること
        $response->assertSee($this->approvedApplication->user->name);
        $response->assertSee('軽微な修正（承認済み）');

        // 対象日時 (checkin_date: 2025/09/20) と 申請日時 (created_at: 2025/09/30) の両方が
        // HTMLに含まれていることを確認
        $response->assertSee('2025/09/20'); // 対象日時
        $response->assertSee('2025/09/30'); // 申請日時

        // 承認待ちの申請は表示されていないこと
        $response->assertDontSee($this->pendingApplication->user->name);
    }

    /**
     * 承認待ち申請の詳細ページ表示と「承認」ボタンの有無を検証します。
     */
    public function test_admin_apply_judgement_index_for_pending_application()
    {
        // admin.apply.judgement.index (管理者申請詳細) も adminUserでアクセス可能と仮定
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.apply.judgement.index', ['attendance_correct_request_id' => $this->pendingApplication->id]));

        $response->assertStatus(200);

        // ページのタイトル、名前を確認
        $response->assertSee('勤怠詳細');
        $response->assertSee('スタッフA');

        // 日付の検証を、全角スペースに依存しない形で分割して検証
        $response->assertSee('2025年');
        $response->assertSee('9月20日');

        // 申請内容の確認（出勤・退勤 -> 申請理由 の主要な順序を確認）
        $response->assertSeeInOrder(['09:10', '18:10', '電車遅延による打刻修正（承認待ち）']);

        // 承認待ちのため、「承 認」ボタンが表示されていること (スペースを含めたテキストで検証)
        $response->assertSee('承 認</button>', false); // <--- ここを修正

        // フォームアクションとIDを確認
        $response->assertSee('<form action="' . route('admin.apply.attendance.approve') . '" method="post">', false);
        $response->assertSee('<input type="hidden" name="id" value="' . $this->pendingApplication->id . '">', false);
    }

    /**
     * 承認済み申請の詳細ページ表示と「承認済み」ボタンの有無を検証します。
     */
    public function test_admin_apply_judgement_index_for_approved_application()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.apply.judgement.index', ['attendance_correct_request_id' => $this->approvedApplication->id]));

        $response->assertStatus(200);

        // ページのタイトル、名前を確認
        $response->assertSee('勤怠詳細');
        $response->assertSee('スタッフB');

        // 日付の検証を、全角スペースに依存しない形で分割して検証
        $response->assertSee('2025年');
        $response->assertSee('9月20日');

        // 申請内容の確認（出勤・退勤 -> 申請理由 の主要な順序を確認）
        $response->assertSeeInOrder(['09:05', '18:05', '軽微な修正（承認済み）']);

        // 承認済みのため、「承認済み」ボタン（disabled）が表示され、「承認」ボタンは表示されていないこと
        $response->assertDontSee('承 認</button>', false); // 念のため、スペース付きで非表示をチェック

        // HTMLログから、承認済みボタンのHTMLを確認し、アサーションを調整
        $response->assertSee('承認済み</button>', false);
        $response->assertSee('disabled', false);
    }
}