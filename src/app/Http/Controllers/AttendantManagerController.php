<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;

class AttendantManagerController extends Controller
{
    /**
     * 打刻勤怠ページを表示します。
     */
    public function user_index()
    {
        // 認証済みユーザーを取得
        $user = Auth::user();
        // 今日の勤怠レコードを取得
        $attendance = Attendance::where('user_id', $user->id)
                                ->whereDate('checkin_date', Carbon::today())
                                ->first();

        // 現在の時間帯に応じて挨拶文を作成
        $now = Carbon::now();
        if ($now->hour >= 6 && $now->hour < 12) {
            $greeting = 'おはようございます、' . $user->name . 'さん';
        } elseif ($now->hour >= 12 && $now->hour < 18) {
            $greeting = 'こんにちは、' . $user->name . 'さん';
        } else {
            $greeting = 'こんばんは、' . $user->name . 'さん';
        }

        // 勤怠データと挨拶データをビューに渡して表示
        return view('user_stamping', compact('attendance', 'greeting'));
    }


    public function user_list_index(Request $request)
    {
        // 認証済みユーザーを取得
        $user = Auth::user();

        // URLパラメータから年と月を取得、なければ現在の日付を使用
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $date = Carbon::create($year, $month, 1);

        // 前月と次月のURLを生成
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();

        // ログインユーザーのIDを取得
        $userId = Auth::id();

        // ユーザーのその月の勤怠レコードを取得
        $attendances = Attendance::where('user_id', $user->id)
                            ->whereYear('checkin_date', $year)
                            ->whereMonth('checkin_date', $month)
                            ->get();

        // ビューに渡すデータを連想配列としてまとめる
        $viewData = [
            'attendances' => $attendances,
            'date' => $date,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'userId' => $userId,
        ];

        // 勤怠データをビューに渡して表示
        return view('user_attendance', $viewData);
    }


     public function user_attendance_detail_index(Request $request, $id = null)
    {
        // 認証済みユーザーを取得
        $user = Auth::user();

        // クエリパラメータから日付を取得、存在しなければ現在の日付
        $date = $request->input('date') ?? Carbon::now()->toDateString();

        // 勤怠データを取得
        $attendance = null;
        if ($id) {
            // URLからIDが渡された場合は、そのIDで検索
            $attendance = Attendance::find($id);
        } else {
            // IDが渡されなかった場合は、日付とユーザーIDで検索
            $attendance = Attendance::where('user_id', $user->id)
                                    ->where('checkin_date', $date)
                                    ->first();
        }

        // 勤怠データが存在するかどうかに関係なく、その日の申請データを検索して取得
        // `$application` を確実に定義する
        $application = Application::where('user_id', $user->id)
                                ->where('checkin_date', $date)
                                ->first();

        // 休憩時間のフォーム入力欄の準備
        $formBreakTimes = [];
        $maxBreaks = 4; // 最大休憩回数を設定
        
        // 既存の休憩データがあれば取得
        $existingBreakCount = 0;
        if ($attendance) {
            for ($i = 1; $i <= $maxBreaks; $i++) {
                $breakStartTime = $attendance->{"break_start_time_{$i}"} ?? '';
                $breakEndTime = $attendance->{"break_end_time_{$i}"} ?? '';
                
                if ($breakStartTime || $breakEndTime) {
                    $formBreakTimes[] = [
                        'start_time' => $breakStartTime ? Carbon::parse($breakStartTime)->format('H:i') : '',
                        'end_time' => $breakEndTime ? Carbon::parse($breakEndTime)->format('H:i') : ''
                    ];
                    $existingBreakCount++;
                }
            }
        }
        
        // 既存の休憩データが2つ未満の場合、空の入力欄を追加
        $minBreaks = 2;
        if ($existingBreakCount < $minBreaks) {
            for ($i = $existingBreakCount; $i < $minBreaks; $i++) {
                $formBreakTimes[] = [
                    'start_time' => '',
                    'end_time' => ''
                ];
            }
        }

        // ビューに渡すデータをまとめる
        $viewData = [
            'attendance' => $attendance,
            'user' => $user,
            // 勤怠データが存在しない場合は、リクエストから取得した$dateを渡す
            'date' => $attendance ? $attendance->checkin_date : $date,
            'formBreakTimes' => $formBreakTimes,
            'application' => $application, // ここで申請データをビューに渡す
        ];

        // 勤怠詳細データをビューに渡して表示
        return view('user_attendance_detail', $viewData);
    }


        public function user_apply_index()
    {
        // 認証済みユーザーを取得
        $user = Auth::user();

        // ユーザーの全勤怠レコードを新しい順に取得
        $attendances = Attendance::where('user_id', $user->id)
                            ->orderBy('checkin_date', 'desc')
                            ->get();

        // 勤怠データをビューに渡して表示
        return view('user_apply', compact('attendances'));
    }


    public function admin_list_index(Request $request)
    {
        // リクエストから日付を取得、なければ今日の日付を使用
        $date = $request->input('date', Carbon::now()->toDateString());

        // 指定された日付の全ユーザーの勤怠データを取得
        $attendances = Attendance::with('user')
            ->whereDate('checkin_date', $date)
            ->orderBy('checkin_date', 'asc')
            ->get();

        return view('admin_attendance', [
            'attendances' => $attendances,
        ]);
    }




        public function admin_staff_list_index(Request $request)
    {
        $users = User::all();

        return view('admin_staff_list', [
            'users' => $users,
        ]);
    }





    public function admin_apply_list_index(Request $request)
    {
        // 'pending'というクエリパラメータを取得。存在しない場合は'false'をデフォルト値とする
        $status = $request->query('pending', 'false');

        // Applicationモデルのクエリを開始
        $query = Application::query();

        // クエリパラメータ'pending'の値に応じてデータをフィルタリング
        if ($status === 'true') {
            // 'pending'がtrueの場合は、承認済みの申請のみを取得
            $query->where('pending', true);
        } else {
            // 'pending'がfalseまたは指定がない場合は、承認待ちの申請のみを取得
            $query->where('pending', false);
        }

        // フィルタリングされた結果を取得
        $applications = $query->get();

        return view('admin_apply', [
            'applications' => $applications,
        ]);
    }


    /**
     * 出勤処理を実行します。
     */
    public function clockIn()
    {
        $user = Auth::user();
        $today = Carbon::today();

        // 今日の勤怠レコードが既に存在するか確認
        $attendance = Attendance::where('user_id', $user->id)
                                ->whereDate('checkin_date', $today)
                                ->first();

        if (is_null($attendance)) {
            // レコードが存在しない場合、新規作成
            Attendance::create([
                'user_id' => $user->id,
                'checkin_date' => $today,
                'clock_in_time' => Carbon::now(),
            ]);
        }

        return redirect()->route('attendance.user.index');
    }

    /**
     * 退勤処理を実行します。
     */
    public function clockOut()
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
                                ->whereDate('checkin_date', $today)
                                ->first();

        // 勤怠レコードが存在し、退勤時間が未登録の場合のみ更新
        if ($attendance && is_null($attendance->clock_out_time)) {
            $now = Carbon::now();

            // Unixタイムスタンプを使用して総勤務時間（秒）を計算
            $totalWorkSeconds = $now->timestamp - strtotime($attendance->clock_in_time);

            // すべての休憩時間を再計算
            $totalBreakSeconds = 0;
            for ($i = 1; $i <= 4; $i++) {
                $start = $attendance->{'break_start_time_' . $i};
                $end = $attendance->{'break_end_time_' . $i};

                if (!empty($start) && !empty($end)) {
                    $totalBreakSeconds += strtotime($end) - strtotime($start);
                }
            }

            // 最終的な労働時間（秒）を計算し、マイナスにならないようにする
            $finalWorkSeconds = max(0, $totalWorkSeconds - $totalBreakSeconds);

            // 労働時間を分単位に変換
            $finalWorkMinutes = round($finalWorkSeconds / 60);

            $attendance->update([
                'clock_out_time' => $now,
                'work_time' => $finalWorkMinutes,
                'break_total_time' => round($totalBreakSeconds / 60),
            ]);
        }

        return redirect()->route('attendance.user.index');
    }

    /**
     * 休憩開始処理を実行します。
     */
    public function breakStart()
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
                                ->whereDate('checkin_date', $today)
                                ->first();

        if ($attendance) {
            // 4回までの休憩に対応するため、空いている最初の休憩開始カラムを探して更新
            for ($i = 1; $i <= 4; $i++) {
                $breakStartColumn = 'break_start_time_' . $i;
                if (empty($attendance->$breakStartColumn)) {
                    $attendance->update([
                        $breakStartColumn => Carbon::now(),
                    ]);
                    break; // 更新後、ループを終了
                }
            }
        }

        return redirect()->route('attendance.user.index');
    }

    /**
     * 休憩終了処理を実行します。
     */
    public function breakEnd()
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
                                ->whereDate('checkin_date', $today)
                                ->first();

        if ($attendance) {
            // 直近の、まだ終了していない休憩を探して終了時間を更新
            for ($i = 4; $i >= 1; $i--) {
                $breakStartColumn = 'break_start_time_' . $i;
                $breakEndColumn = 'break_end_time_' . $i;

                if (!empty($attendance->$breakStartColumn) && empty($attendance->$breakEndColumn)) {
                    $now = Carbon::now();
                    $attendance->update([
                        $breakEndColumn => $now,
                    ]);
                    break; // 更新後、ループを終了
                }
            }
        }

        return redirect()->route('attendance.user.index');
    }


        /**
     * 勤怠データを保存（新規作成または更新）する
     * * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function attendance_update(Request $request)
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
        $application->pending = true; // 新しい申請は承認待ち状態（true）として保存

        // 勤怠IDが存在する場合のみセット
        if ($attendanceId) {
            $application->attendance_id = $attendanceId;
        }

        // 日付と時間を結合してDateTimeオブジェクトを作成
        if (!empty($checkinTime)) {
            $application->clock_in_time = Carbon::parse($date . ' ' . $checkinTime);
        }
        if (!empty($checkoutTime)) {
            $application->clock_out_time = Carbon::parse($date . ' ' . $checkoutTime);
        }
        
        // 休憩時間を個別のカラムに設定
        for ($i = 0; $i < count($breakTimes) && $i < 4; $i++) {
            $breakStartTime = trim($breakTimes[$i]['start_time'] ?? '');
            $breakEndTime = trim($breakTimes[$i]['end_time'] ?? '');
            
            if (!empty($breakStartTime)) {
                $application->{'break_start_time_' . ($i + 1)} = Carbon::parse($date . ' ' . $breakStartTime);
            }
            if (!empty($breakEndTime)) {
                $application->{'break_end_time_' . ($i + 1)} = Carbon::parse($date . ' ' . $breakEndTime);
            }
        }

        $application->reason = $reason;
        
        // work_timeはここでは計算せずnullのまま保存
        $application->work_time = null;
        // break_total_timeも同様にここでは計算せずnullのまま保存
        $application->break_total_time = null;

        $application->save();

        return redirect()->route('user.attendance.detail.index', ['date' => $date])->with('success', '勤怠修正の申請を送信しました。');
    }

}