<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use App\Http\Requests\ApplicationAndAttendantRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log; // 大規模なプロジェクトの時のためLogファサードのインポートを追加

class UserAttendantManagerController extends Controller
{
    /**
     * ユーザー打刻画面の表示と状態判定（日跨ぎ対応）
     */
    public function user_stamping_index()
    {
        // 認証済みユーザーを取得
        $user = Auth::user();

        // ---------------------------------------------
        // ★★★ 修正箇所: 日跨ぎ対応ロジックを優先 ★★★
        // ---------------------------------------------
        
        // 1. 最優先: clock_out_time が null (未退勤) のレコードを取得する。
        //    これにより、昨日出勤し、現在が日を跨いでいてもそのレコードが「現在の勤務」となる。
        $attendance = Attendance::where('user_id', $user->id)
                                ->whereNull('clock_out_time')
                                ->orderByDesc('checkin_date') // 複数あった場合は最新の日付のもの
                                ->first();

        // 2. 未退勤のレコードがない場合のみ、今日の完了したレコードを取得する（日を跨がずに勤務が終了した場合など）
        //    ※ 昨日の勤務が完了している場合、この処理はスキップされ $attendance は null のままになる
        if (!$attendance) {
            $attendance = Attendance::where('user_id', $user->id)
                                    ->whereDate('checkin_date', Carbon::today())
                                    ->first();
        }

        // 3. 勤務状態を判定
        $isClockedIn = isset($attendance) && isset($attendance->clock_in_time);
        $isClockedOut = isset($attendance) && isset($attendance->clock_out_time);

        $isBreaking = false;
        if (isset($attendance)) {
            // break_timeが配列であればそのまま、文字列(JSON)であればデコードを試みる
            $breakTimeData = is_array($attendance->break_time)
                             ? $attendance->break_time
                             : (is_string($attendance->break_time) ? json_decode($attendance->break_time, true) : null);

            if ($breakTimeData && is_array($breakTimeData) && !empty($breakTimeData)) {
                $lastBreak = end($breakTimeData);
                // 最後の休憩レコードの 'end' が空であれば休憩中と判定
                if (isset($lastBreak['start']) && empty($lastBreak['end'])) {
                    $isBreaking = true;
                }
            }
        }

        // 4. 現在の日時情報を取得 (ビューの初期表示用) および 挨拶文作成の基準時刻
        date_default_timezone_set('Asia/Tokyo');
        $now = Carbon::now();

        $currentDate = $now->format('Y年m月d日');
        $dayOfWeek = $now->dayOfWeek; // Carbon::dayOfWeek は 0(日)～6(土) を返す
        $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];
        $currentDay = $dayOfWeekMap[$dayOfWeek];
        $currentTime = $now->format('H:i');

        // 6. 必要なデータをすべてビューに渡す
        return view('user-stamping', compact(
            'attendance',
            'isClockedIn',
            'isClockedOut',
            'isBreaking',
            'currentDate',
            'currentDay',
            'currentTime'
        ));
    }


    /**
     * 月別勤怠一覧ページを表示します。
     */
    public function user_month_index(Request $request)
    {
        // 認証済みユーザーを取得
        $user = Auth::user();

        // URLパラメータから年と月を取得、なければ現在の日付を使用
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $date = Carbon::createFromDate($year, $month, 1);

        // 前月と次月のURLを生成
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();

        // ログインユーザーのIDを取得
        $userId = Auth::id();

        // ユーザーのその月の勤怠レコードを日付をキーとするコレクションで取得
        $attendances = Attendance::where('user_id', $user->id)
                            ->whereYear('checkin_date', $year)
                            ->whereMonth('checkin_date', $month)
                            ->get()
                            // 日付（Y-m-d）をキーとしてコレクションを再構成
                            ->keyBy(function ($item) {
                                return Carbon::parse($item->checkin_date)->format('Y-m-d');
                            });

        $formattedAttendanceData = [];
        $daysInMonth = $date->daysInMonth;
        
        // ★追加: 今日の日付を取得 (比較に使用)
        $today = Carbon::now()->startOfDay();

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $currentDay = Carbon::createFromDate($year, $month, $i);
            $dateKey = $currentDay->format('Y-m-d');
            $attendance = $attendances->get($dateKey);
            
            $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];
            $dayOfWeek = $dayOfWeekMap[$currentDay->dayOfWeek];

            $data = [
                'day_label' => "{$month}/{$currentDay->format('d')}({$dayOfWeek})",
                'is_weekend' => $currentDay->dayOfWeek == 0 || $currentDay->dayOfWeek == 6,
                'date_key' => $dateKey, // ★追加: 日付文字列をBladeに渡す
                'clock_in' => '',
                'clock_out' => '',
                'break_time' => '',
                'work_time' => '',
                // デフォルトのURLを、勤怠データがない場合を想定して生成
                'detail_url' => route('user.attendance.detail.index', ['date' => $dateKey]), 
                'attendance_id' => null, 
                // ★追加: 詳細ボタンの表示制御のためにCarbonオブジェクトを追加
                'current_day_carbon' => $currentDay, 
            ];

            if ($attendance) {
                // 退勤時間が記録されているか、かつ出勤時間と同じ値ではないかチェック
                $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;
                
                $data['clock_in'] = Carbon::parse($attendance->clock_in_time)->format('H:i');
                
                // 退勤時間は打刻があれば表示
                $data['clock_out'] = $hasClockedOut ? Carbon::parse($attendance->clock_out_time)->format('H:i') : '';
                
                // 休憩時間 (分を H:i 形式に変換)
                if ($attendance->break_total_time !== null) {
                    $minutes = $attendance->break_total_time;
                    $data['break_time'] = floor($minutes / 60) . ':' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT);
                }

                // 合計勤務時間 (分を H:i 形式に変換)
                if ($attendance->work_time !== null) {
                    $minutes = $attendance->work_time;
                    $data['work_time'] = floor($minutes / 60) . ':' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT);
                }

                // 勤怠IDと日付の両方を渡すことで、詳細コントローラーのロジックを安定させる
                $data['detail_url'] = route('user.attendance.detail.index', ['id' => $attendance->id, 'date' => $dateKey]);
                $data['attendance_id'] = $attendance->id;
            }
            
            $formattedAttendanceData[] = $data;
        }

        // ビューに渡すデータを連想配列としてまとめる
        $viewData = [
            'formattedAttendanceData' => $formattedAttendanceData, // 整形済みデータ
            'date' => $date,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'today' => $today, // ★追加: 今日（システムの日付）をビューに渡す
        ];

        // 勤怠データをビューに渡して表示
        return view('user-month-attendance', $viewData);
    }


    /**
     * 勤怠詳細表示用のデータを取得します。
     */
    public function user_attendance_detail_index(Request $request, $id = null)
    {
        // 認証済みユーザーを取得 (ログインしているスタッフ)
        $loggedInUser = Auth::user();

        // クエリパラメータから日付を取得 (フォールバックとして使用)
        // 初期値として $date を確定
        $date = $request->input('date') ?? Carbon::now()->toDateString();

        $attendance = null;
        $targetUserId = $loggedInUser->id; // スタッフ自身が対象

        // ----------------------------------------------------
        // 1. 勤怠データ ($attendance) の取得と日付の確定
        // ----------------------------------------------------

        if ($id) {
            // $id が渡された場合、Attendance IDとして検索を試みる
            $tempAttendance = Attendance::find($id);
            
            // 勤怠データが見つかり、かつそれがログインユーザーのものであることを確認
            if ($tempAttendance && $tempAttendance->user_id == $loggedInUser->id) {
                // (A) Attendance IDで勤怠データが見つかった場合
                $attendance = $tempAttendance;
                // ★最重要: 勤怠レコードが持つ日付を、この詳細画面の正しい日付として確定する
                $date = Carbon::parse($attendance->checkin_date)->toDateString();
            } else {
                // (B) IDで見つからない、または他人のデータの場合、URLの$dateを基に再検索
                $attendance = Attendance::where('user_id', $loggedInUser->id)
                                        ->whereDate('checkin_date', $date)
                                        ->first();
            }
        } else {
            // (C) $id が渡されなかった場合（勤怠データがない日の詳細）
            $attendance = Attendance::where('user_id', $loggedInUser->id)
                                    ->whereDate('checkin_date', $date)
                                    ->first();
        }
        
        // ターゲットユーザーはログインユーザーで固定
        $targetUser = $loggedInUser;

        // ----------------------------------------------------
        // 2. 申請データ ($application) の取得 (確定した$dateを使用)
        // ----------------------------------------------------
        
        // 2-1. 確定した$dateのcheckin_dateを持つ申請を検索（標準的な検索）
        $application = Application::where('user_id', $targetUser->id)
                                ->whereDate('checkin_date', $date)
                                ->first();

        // ★日跨ぎ対応の修正: 申請が見つからない場合、前日の申請が現在の$dateを跨いでいるか確認
        if (!$application) {
            $prevDate = Carbon::parse($date)->subDay()->toDateString();
            
            $application = Application::where('user_id', $targetUser->id)
                ->whereDate('checkin_date', $prevDate) // 前日の申請を検索
                // ... AND その退勤時刻が現在の$dateの開始時刻（00:00:00）より後であること
                ->where('clock_out_time', '>', Carbon::parse($date)->startOfDay()->toDateTimeString())
                ->first();

            // 補足: break_timeはJSONとして保存されているため、純粋なDBクエリでの時間跨ぎチェックは複雑です。
            // 休憩時間は通常勤務時間内にあるため、clock_out_timeのチェックが日跨ぎ判断として最も適切です。
        }
        // ----------------------------------------------------
        // 3. フォーム初期値 ($sourceData) の決定（申請データ優先）
        // ----------------------------------------------------
        $sourceData = $application ?? $attendance; 

        // ----------------------------------------------------
        // 4. 休憩時間のフォーム入力欄の準備
        // ----------------------------------------------------
        $formBreakTimes = [];

        if ($sourceData && isset($sourceData->break_time)) {
            // break_timeがJSON文字列であればデコードを試みる
            $breakTimes = is_array($sourceData->break_time) ? $sourceData->break_time : json_decode($sourceData->break_time, true);

            if (is_array($breakTimes)) {
                foreach ($breakTimes as $break) {
                    $start = $break['start'] ?? null;
                    $end = $break['end'] ?? null;

                    if ($start || $end) {
                        $formBreakTimes[] = [
                            // 日付部分を除き、時刻のみを 'H:i' 形式でセット
                            'start_time' => $start ? Carbon::parse($start)->format('H:i') : '',
                            'end_time' => $end ? Carbon::parse($end)->format('H:i') : ''
                        ];
                    }
                }
            }
        }
        
        // ----------------------------------------------------
        // 常に1つの空の休憩フォームを無条件に追加する
        // ----------------------------------------------------
        $formBreakTimes[] = [
            'start_time' => '',
            'end_time' => ''
        ];
        
        // ----------------------------------------------------
        // 5. ビューに渡すデータをまとめる
        // ----------------------------------------------------
        $viewData = [
            'attendance' => $attendance,
            'user' => $targetUser, 
            'date' => Carbon::parse($date)->toDateString(), 
            'formBreakTimes' => $formBreakTimes, 
            'application' => $application,
            'primaryData' => $sourceData, 
        ];

        return view('user-attendance-detail', $viewData);
    }


    /**
     * 認証ユーザー自身の申請一覧を表示する。
     */
    public function user_apply_index(Request $request)
    {
        // 認証ユーザーのIDを取得
        $userId = Auth::id();

        // 'pending'というクエリパラメータを取得。存在しない場合は'true'をデフォルト値とする
        $status = $request->query('pending', 'true');

        // 認証ユーザーの申請のみをフィルタリング
        $query = Application::with('user')->where('user_id', $userId);

        // クエリパラメータ'pending'の値に応じてデータをフィルタリング
        if ($status === 'true') {
            // 'pending'がtrueの場合は、承認待ちの申請のみを取得
            $query->where('pending', true);
        } else {
            // 'pending'がfalseまたは指定がない場合は、承認済みの申請のみを取得
            $query->where('pending', false);
        }

        // 最新のものが上に来るように降順でソートして取得
        $applications = $query->orderBy('created_at', 'desc')->get();

        // ---------------------------------------------
        // ★★★ 修正箇所: 対象日時を checkin_date に変更 ★★★
        // ---------------------------------------------
        $formattedApplications = $applications->map(function ($application) {
            $targetDate = null;
            $targetDateDisplay = '-';
            $detailUrl = '#'; // デフォルトで無効なリンクを設定

            if ($application->checkin_date) {
                // 💡 修正: 対象日付を checkin_date に設定
                $carbonCheckinDate = Carbon::parse($application->checkin_date);
                
                // 詳細リンクに渡す Y-m-d 形式の日付
                $targetDate = $carbonCheckinDate->format('Y-m-d');
                
                // テーブルに表示する Y/m/d 形式の日付
                $targetDateDisplay = $carbonCheckinDate->format('Y/m/d');
                
                // 詳細URLを生成（checkin_date ベースのルートを使用）
                $detailUrl = route('user.attendance.detail.index', ['date' => $targetDate]);
            }
            
            return [
                'id' => $application->id, // ★ IDを追加
                'status_text' => $application->pending ? '承認待ち' : '承認済み',
                // 'status_color' => $application->pending ? 'orange' : 'green',
                'user_name' => $application->user->name,
                'target_date_display' => $targetDateDisplay, // 整形済み日付 (checkin_date)
                'reason' => $application->reason,
                // 💡 修正箇所: 申請日時から時間情報を削除し、Y/m/d のみを使用
                'created_at_display' => $application->created_at->format('Y/m/d'),
                'detail_url' => $detailUrl,
                'has_target_date' => (bool)$targetDate, // 日付が有効かどうかのフラグ
                'pending' => $application->pending,
            ];
        });

        return view('user-apply-list', [
            // 整形済みのデータをビューに渡す
            'applications' => $formattedApplications, 
        ]);
    }


    /**
     * 出勤処理を実行します。（日跨ぎ対応：二重出勤をチェック）
     */
    public function clockIn()
    {
        $user = Auth::user();

        // 進行中の未退勤レコードがないか確認 (日跨ぎ対応)
        $existingAttendance = Attendance::where('user_id', $user->id)
                                        ->whereNull('clock_out_time')
                                        ->first();

        if (is_null($existingAttendance)) {
            // 未退勤レコードがない場合のみ、新規作成
            Attendance::create([
                'user_id' => $user->id,
                'checkin_date' => Carbon::today(), // 出勤打刻日
                'clock_in_time' => Carbon::now(),
            ]);
        } else {
            // 既に進行中の勤務がある場合は、二重出勤を防ぐためにリダイレクト
            return redirect()->route('user.stamping.index')->with('error', '既に勤務中です。退勤処理を完了してください。');
        }

        return redirect()->route('user.stamping.index');
    }

    /**
     * 退勤処理を実行します。（JSON休憩対応 / 日跨ぎ対応）
     */
    public function attendance_create()
    {
        $user = Auth::user();

        // 進行中の未退勤レコードを探す (日跨ぎ対応)
        $attendance = Attendance::where('user_id', $user->id)
                                ->whereNull('clock_out_time')
                                ->orderByDesc('checkin_date')
                                ->first();

        // 記録があり、まだ退勤時刻が打刻されていない場合のみ処理を実行
        if ($attendance) {
            $now = Carbon::now();
            
            // 出勤時刻をCarbonオブジェクトに変換
            $clockInCarbon = $attendance->clock_in_time ? Carbon::parse($attendance->clock_in_time) : null;

            if (!$clockInCarbon) {
                 // 出勤時刻がない場合は処理をスキップまたはエラー
                 Log::warning('退勤処理エラー: ' . $user->id . 'の出勤時刻が見つかりません。');
                 return redirect()->route('user.stamping.index')->with('error', '出勤時刻の記録がないため、退勤処理を完了できません。');
            }
            
            // break_time JSONカラムを配列として取得
            $breakTimes = is_array($attendance->break_time) ? $attendance->break_time : json_decode($attendance->break_time, true) ?? []; 

            // 1. 総休憩時間（秒）をJSON配列から計算
            $totalBreakSeconds = 0;
            foreach ($breakTimes as $break) {
                if (!empty($break['start']) && !empty($break['end'])) { 
                    $start = Carbon::parse($break['start']);
                    $end = Carbon::parse($break['end']);
                    
                    if ($end->gt($start)) {
                       // Carbonのtimestamp差分で休憩時間を計算
                       $totalBreakSeconds += $end->timestamp - $start->timestamp;
                    }
                }
            }

            // 2. 総労働時間（秒）を計算 (日跨ぎも正確)
            $totalWorkSeconds = 0;
            if ($now->gt($clockInCarbon)) {
                $totalWorkSeconds = $now->timestamp - $clockInCarbon->timestamp;
            }

            // 3. 最終的な労働時間（秒）を計算し、分単位に変換
            $finalWorkSeconds = max(0, $totalWorkSeconds - $totalBreakSeconds);
            $finalWorkMinutes = round($finalWorkSeconds / 60);
            $totalBreakMinutes = round($totalBreakSeconds / 60);

            $attendance->update([
                'clock_out_time' => $now,
                'work_time' => $finalWorkMinutes,
                'break_total_time' => $totalBreakMinutes,
            ]);
        }

        return redirect()->route('user.stamping.index');
    }

    /**
     * 休憩開始処理を実行します。（JSON休憩対応 / 日跨ぎ対応）
     */
    public function breakStart()
    {
        $user = Auth::user();

        // 進行中の未退勤レコードを探す (日跨ぎ対応)
        $attendance = Attendance::where('user_id', $user->id)
                                ->whereNull('clock_out_time')
                                ->orderByDesc('checkin_date')
                                ->first();

        if ($attendance) {
            // break_timeを取得。JSON castが設定されていない可能性を考慮し、配列化を試みる。
            $breakTimes = is_array($attendance->break_time) ? $attendance->break_time : json_decode($attendance->break_time, true) ?? [];

            // 最後の休憩データを取り出し、終了しているかチェック
            $lastBreak = end($breakTimes);

            // 最後の休憩が既に開始されていて、かつ終了していない場合、二重開始を防ぐ
            if ($lastBreak && empty($lastBreak['end'])) {
                // 何もしない (二重開始を防ぐ)
            } else {
                // 新しい休憩開始を追加
                $breakTimes[] = [
                    'start' => Carbon::now()->toDateTimeString(),
                    'end' => null, 
                ];

                $attendance->update([
                    'break_time' => $breakTimes,
                ]);
            }
        }

        return redirect()->route('user.stamping.index');
    }

    /**
     * 休憩終了処理を実行します。（JSON休憩対応 / 日跨ぎ対応）
     */
    public function breakEnd()
    {
        $user = Auth::user();

        // 進行中の未退勤レコードを探す (日跨ぎ対応)
        $attendance = Attendance::where('user_id', $user->id)
                                ->whereNull('clock_out_time')
                                ->orderByDesc('checkin_date')
                                ->first();

        if ($attendance) {
            // break_timeを取得。JSON castが設定されていない可能性を考慮し、配列化を試みる。
            $breakTimes = is_array($attendance->break_time) ? $attendance->break_time : json_decode($attendance->break_time, true) ?? [];
            $updated = false;

            // 配列を逆順にチェックし、'end'がnullのものを探す（直近の未終了休憩）
            foreach (array_reverse($breakTimes, true) as $key => $break) {
                if (empty($break['end'])) {
                    // 終了時間を設定し、ループを抜ける
                    $breakTimes[$key]['end'] = Carbon::now()->toDateTimeString();
                    $updated = true;
                    break;
                }
            }

            if ($updated) {
                // 1. 総休憩時間（秒）をJSON配列から計算
                $totalBreakSeconds = 0;
                foreach ($breakTimes as $break) {
                    if (!empty($break['start']) && !empty($break['end'])) { 
                        $start = Carbon::parse($break['start']);
                        $end = Carbon::parse($break['end']);
                        
                        if ($end->gt($start)) {
                           $totalBreakSeconds += $end->timestamp - $start->timestamp;
                        }
                    }
                }
                
                // 2. 総休憩時間を分単位に変換
                $totalBreakMinutes = round($totalBreakSeconds / 60);

                // 3. break_time と break_total_time の両方を更新
                $attendance->update([
                    'break_time' => $breakTimes,
                    'break_total_time' => $totalBreakMinutes, // 休憩終了時に総休憩時間を更新
                ]);
            }
        }

        return redirect()->route('user.stamping.index');
    }


    /**
     * 勤怠データを保存（新規作成または更新）する (JSON休憩対応/日跨ぎ補正)
     */
    public function application_create(ApplicationAndAttendantRequest $request)
    {
        // 認証済みユーザーを取得
        $user = Auth::user();

        // フォームから送信された勤怠IDを取得
        $attendanceId = $request->input('attendance_id');

        // フォームから送信されたデータを取得
        $date = $request->input('checkin_date');
        $checkinTime = trim($request->input('clock_in_time'));
        $checkoutTime = trim($request->input('clock_out_time'));
        $reason = trim($request->input('reason'));
        $breakTimes = $request->input('break_times', []); 

        // 勤怠データを applications テーブルに保存
        $application = new Application();
        $application->user_id = $user->id;
        $application->checkin_date = $date;
        $application->pending = true; 

        // 勤怠IDが存在する場合のみセット
        if ($attendanceId) {
            $application->attendance_id = $attendanceId;
        }

        $clockInCarbon = null;
        $clockOutCarbon = null;

        // 出勤時刻を設定
        if (!empty($checkinTime)) {
            $clockInCarbon = Carbon::parse($date . ' ' . $checkinTime);
            $application->clock_in_time = $clockInCarbon;
        }
        
        // 退勤時刻を設定し、日跨ぎを補正
        if (!empty($checkoutTime)) {
            $clockOutCarbon = Carbon::parse($date . ' ' . $checkoutTime);
            
            // 💡 修正箇所1: 退勤時刻が出勤時刻よりも前なら翌日に補正
            if ($clockInCarbon && $clockOutCarbon->lt($clockInCarbon)) {
                 $clockOutCarbon = $clockOutCarbon->addDay();
            }
            $application->clock_out_time = $clockOutCarbon;
        }

        // --- 修正箇所2: 休憩時間をJSON配列として構築し、日跨ぎを補正 ---
        $breakTimeJsonArray = [];
        foreach ($breakTimes as $breakTime) {
            $breakStartTime = trim($breakTime['start_time'] ?? '');
            $breakEndTime = trim($breakTime['end_time'] ?? '');

            if (!empty($breakStartTime) && !empty($breakEndTime)) {
                $breakStartCarbon = Carbon::parse($date . ' ' . $breakStartTime);
                $breakEndCarbon = Carbon::parse($date . ' ' . $breakEndTime);
                
                // 💡 修正箇所2: 休憩終了時刻が開始時刻よりも前なら翌日に補正
                if ($breakEndCarbon->lt($breakStartCarbon)) {
                    $breakEndCarbon = $breakEndCarbon->addDay();
                }

                $breakTimeJsonArray[] = [
                    'start' => $breakStartCarbon->toDateTimeString(),
                    'end' => $breakEndCarbon->toDateTimeString(),
                ];
            }
        }
        
        // break_time JSONカラムに設定
        $application->break_time = $breakTimeJsonArray;
        // --- 修正箇所2: 終了 ---

        $application->reason = $reason;

        // work_time, break_total_timeは承認時に計算されるためnullのまま保存
        $application->work_time = null;
        $application->break_total_time = null;

        $application->save();

        // ----------------------------------------------------------------------
        // ★修正箇所: セッションメッセージを動的に生成
        // ----------------------------------------------------------------------
        // 日付を「〇月〇日」形式に整形
        $displayDate = Carbon::parse($date)->isoFormat('M月D日');
        
        $successMessage = "{$user->name}さん、{$displayDate}の修正申請を受け付けました。";

        return redirect()->route('user.month.index', ['date' => $date])->with('success', $successMessage);
    }
}