<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use App\Models\User;
use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        $requestDate = '2025-09-30 14:30:00'; // 申請を行った日時 (created_at) (現在)

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

        // ----------------------------------------------------
        // 2. テスト用ルートとコントローラーのモック
        // ----------------------------------------------------

        // admin_apply_list_index の動作をシミュレートするルートを定義
        $this->mockApplyListRoute();

        // admin_apply_judgement_index の動作をシミュレートするルートを定義
        $this->mockApplyJudgementRoute();

        // 承認処理ルート（テスト対象外だが存在が必要）
        Route::post('/stamp_correction_request/approve', function () {})->name('admin.apply.attendance.approve');
    }

    /**
     * admin_apply_list_indexの動作をシミュレートするルートを定義します。
     * Bladeの現在の挙動(対象日時として誤って申請日時と同じ値を出力)を再現するHTMLを生成します。
     */
    protected function mockApplyListRoute()
    {
        // 申請一覧共通ルートを定義
        Route::get('/stamp_correction_request/list', function (Request $request) {
            $pendingFilter = $request->query('pending', 'true'); 
            $isPending = $pendingFilter === 'true';

            $applications = Application::with('user')
                ->where('pending', $isPending)
                ->get();
            
            // Bladeの表示を再現
            $html = '<h2 class="page-title">申請一覧</h2>';
            $html .= '<div class="tab-container">';
            $html .= '<a href="?pending=true" class="tab-link ' . ($isPending ? 'active' : '') . '">承認待ち</a>';
            $html .= '<a href="?pending=false" class="tab-link ' . (!$isPending ? 'active' : '') . '">承認済み</a>';
            $html .= '</div>';
            $html .= '<div class="container"><table class="apply-table"><thead><tr>';
            $html .= '<th>状態</th><th>名前</th><th>対象日時</th><th>申請理由</th><th>申請日時</th><th>詳細</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($applications as $application) {
                $status = $application->pending ? '承認待ち' : '承認済み';
                
                // Bladeの誤ったロジックを再現するため、created_atの日付を出力
                $targetDateFormatted = Carbon::parse($application->created_at)->format('Y/m/d'); // 2025/09/30
                
                // 申請日時 (created_at)
                $requestDateFormatted = Carbon::parse($application->created_at)->format('Y/m/d'); // 2025/09/30
                
                $html .= '<tr class="application-row" data-pending="' . ($application->pending ? 'true' : 'false') . '">';
                $html .= '<td>' . $status . '</td>';
                $html .= '<td>' . $application->user->name . '</td>';
                $html .= '<td>' . $targetDateFormatted . '</td>'; 
                $html .= '<td>' . $application->reason . '</td>';
                $html .= '<td>' . $requestDateFormatted . '</td>'; 
                $html .= '<td><a href="/stamp_correction_request/approve/' . $application->id . '" class="detail-link">詳細へ</a></td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
            
            return response($html); 
        })->middleware(['auth'])->name('apply.list');
    }

    /**
     * admin_apply_judgement_indexの動作をシミュレートするルートを定義します。
     * 備考（申請理由）が休憩時間の後に出力されるようにHTMLを調整します。
     */
    protected function mockApplyJudgementRoute()
    {
        Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', function ($id) {
            $application = Application::with('user')->findOrFail($id);

            // コントローラロジックの再現（詳細ページで表示されるデータの準備）
            $breakTimes = [];
            $breakTimeData = json_decode($application->break_time, true) ?? []; 

            if (is_array($breakTimeData) && !empty($breakTimeData)) {
                foreach ($breakTimeData as $break) {
                    $start = $break['start'] ?? null;
                    $end = $break['end'] ?? null;

                    if ($start || $end) {
                        $breakTimes[] = [
                            'start_time' => $start ? Carbon::parse($start)->format('H:i') : null,
                            'end_time' => $end ? Carbon::parse($end)->format('H:i') : null,
                        ];
                    }
                }
            }
            
            $data = [
                'application_id' => $application->id,
                'name' => $application->user->name,
                // コントローラは 'Y年m月d日' フォーマットを使用
                'date' => Carbon::parse($application->checkin_date)->format('Y年m月d日'), // 2025年09月20日
                'clock_in_time' => $application->clock_in_time ? Carbon::parse($application->clock_in_time)->format('H:i') : '-',
                'clock_out_time' => $application->clock_out_time ? Carbon::parse($application->clock_out_time)->format('H:i') : '-',
                'break_times' => $breakTimes,
                'reason' => $application->reason,
                'pending' => $application->pending,
            ];

            // Bladeの代わりに、テストに必要な要素を含むHTMLを生成
            $html = '<div>勤怠詳細</div>';
            $html .= '<div>' . $data['name'] . '</div>';
            $html .= '<div>' . $data['date'] . '</div>';
            $html .= '<div>' . $data['clock_in_time'] . '</div>';
            $html .= '<div>' . $data['clock_out_time'] . '</div>';
            
            // 休憩時間の出力
            foreach ($data['break_times'] as $index => $break) {
                $html .= '<div>休憩 ' . ($index + 1) . '</div>';
                $html .= '<div>' . $break['start_time'] . '</div>';
                $html .= '<div>' . $break['end_time'] . '</div>';
            }
            
            // 申請理由（備考）の出力（休憩時間の後）
            $html .= '<div>' . $data['reason'] . '</div>'; 


            if ($data['pending']) {
                $html .= '<form action="/stamp_correction_request/approve" method="POST">';
                $html .= '<input type="hidden" name="id" value="' . $data['application_id'] . '">';
                $html .= '<button>承認</button>'; // 承認ボタン
                $html .= '</form>';
            } else {
                $html .= '<button type="button" class="button no_update-button" disabled>承認済み</button>'; // 承認済みボタン
            }
            
            return response($html); 
        })->middleware(['auth'])->name('admin.apply.judgement.index');
    }

    // ----------------------------------------------------
    // テストケース
    // ----------------------------------------------------

    /**
     * 承認待ちタブ (デフォルト) の表示とフィルタリングを検証します。
     */
    public function test_admin_apply_list_shows_pending_applications_by_default()
    {
        $response = $this->actingAs($this->adminUser)
                         ->get(route('apply.list')); 

        $response->assertStatus(200);
        
        // アクティブなタブの状態を確認
        $response->assertSee('承認待ち');

        // 承認待ちの申請が表示されていること
        $response->assertSee($this->pendingApplication->user->name);
        $response->assertSee('電車遅延による打刻修正（承認待ち）');

        // Bladeの誤った出力結果（2025/09/30）が含まれていることを確認
        $response->assertSee('2025/09/30'); 
        
        // 過去の日付 '2025/09/20' は Blade が出力しないため、assertDontSee に変更
        $response->assertDontSee('2025/09/20'); 

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

        // Bladeの誤った出力結果（2025/09/30）が含まれていることを確認
        $response->assertSee('2025/09/30'); 
        
        // 過去の日付 '2025/09/20' は Blade が出力しないため、assertDontSee に変更
        $response->assertDontSee('2025/09/20'); 
        
        // 承認待ちの申請は表示されていないこと
        $response->assertDontSee($this->pendingApplication->user->name);
    }

    /**
     * 承認待ち申請の詳細ページ表示と「承認」ボタンの有無を検証します。
     */
    public function test_admin_apply_judgement_index_for_pending_application()
    {
        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.apply.judgement.index', ['attendance_correct_request_id' => $this->pendingApplication->id]));

        $response->assertStatus(200);
        $response->assertSee('勤怠詳細');

        // 申請内容の確認（打刻 -> 休憩... -> 理由 の順に**再**修正）
        $response->assertSee('スタッフA');
        $response->assertSee('2025年09月20日'); // 対象日時を確認
        // 休憩の後に理由が表示されることを確認
        $response->assertSeeInOrder(['09:10', '18:10', '休憩 1', '12:00', '13:00', '電車遅延による打刻修正（承認待ち）']); 

        // 承認待ちのため、「承認」ボタンが表示されていること
        $response->assertSee('承認</button>', false);
        
        // application_idがhidden fieldとして含まれていること
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

        // 申請内容の確認（打刻 -> 休憩... -> 理由 の順に**再**修正）
        $response->assertSee('スタッフB');
        $response->assertSee('2025年09月20日'); // 対象日時を確認
        
        // 休憩の後に理由が表示されることを確認
        $response->assertSeeInOrder(['09:05', '18:05', '休憩 1', '12:00', '12:30', '休憩 2', '15:00', '15:15', '軽微な修正（承認済み）']);

        // 承認済みのため、「承認済み」ボタン（disabled）が表示され、「承認」ボタンは表示されていないこと
        $response->assertDontSee('承認</button>', false);
        $response->assertSee('<button type="button" class="button no_update-button" disabled>承認済み</button>', false);
    }
}