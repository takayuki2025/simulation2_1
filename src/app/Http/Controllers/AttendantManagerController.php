<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // 大規模なプロジェクトの時のためLogファサードのインポートを追加
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendantManagerController extends Controller
{
    /**
     * 打刻勤怠ページを表示します。
     */
    public function user_stamping_index()
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


    public function user_month_index(Request $request)
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
            'year' => $year, // ★追加: yearをビューに渡す
            'month' => $month, // ★追加: monthをビューに渡す
        ];

        // 勤怠データをビューに渡して表示
        return view('user_month_attendance', $viewData);
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
        $query = Application::where('user_id', $userId);

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

        return view('user_apply_list', [
            'applications' => $applications,
        ]);
    }


    /**
     * 管理者用日次勤怠一覧を表示
     */
    public function admin_staff_daily_index(Request $request)
    {
        // URLパラメータから日付を取得、なければ今日の日付を使用
        $date = $request->get('date', Carbon::now()->toDateString());

        // 指定された日付の全ユーザーの勤怠レコードを取得
        $attendances = Attendance::where('checkin_date', $date)
                                ->with('user')
                                ->get();

        // ユーザーの一覧を取得し、IDが1のユーザーを除外する
        $users = User::where('id', '!=', 1)->get();

        return view('admin_staff_daily_attendance', compact('attendances', 'users'));
    }


    public function admin_user_attendance_detail_index(Request $request, $id = null)
    {
        // クエリパラメータからユーザーID（スタッフのID）と日付を取得
        $userId = $request->input('user_id') ?? $id;
        $date = $request->input('date') ?? Carbon::now()->toDateString();
        
        // 対象スタッフのユーザー情報を取得
        $staffUser = User::findOrFail($userId);

        // 勤怠データを取得
        $attendance = Attendance::where('user_id', $userId)
                                ->where('checkin_date', $date)
                                ->first();

        // 勤怠データが存在するかどうかに関係なく、その日の申請データを検索して取得
        $application = Application::where('user_id', $userId)
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
            // 修正点: staffUserを渡す
            'user' => $staffUser,
            'date' => $date,
            'formBreakTimes' => $formBreakTimes,
            'application' => $application,
        ];

        // 勤怠詳細データをビューに渡して表示
        return view('admin_attendance_detail', $viewData);
    }


        public function admin_staff_list_index(Request $request)
    {
        $users = User::all();

        return view('admin_staff_list', [
            'users' => $users,
        ]);
    }


    public function admin_staff_month_index(Request $request, $id)
    {
        // URLパラメータから年と月を取得、なければ現在の日付を使用
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $date = Carbon::create($year, $month, 1);

        // 前月と次月のURLを生成
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();


        // ★修正点1: 指定されたIDのユーザーを1人だけ取得
        $staffUser = User::findOrFail($id);


        // 指定されたIDのユーザーとその月の勤怠レコードを取得
        $users = User::all();
        $attendances = Attendance::where('user_id', $id)
            ->whereYear('checkin_date', $year)
            ->whereMonth('checkin_date', $month)
            ->get();
        
        $viewData = [
            'attendances' => $attendances,
            'date' => $date,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            // ★修正点2: 取得した特定のユーザーデータをビューに渡す
            'staffUser' => $staffUser,
            'userId' => $id,
            // ★修正点: ビューで必要となる年と月の変数を追加
            'year' => $year,
            'month' => $month,
        ];

        return view('admin_staff_month_attendance', $viewData);
    }


    public function admin_apply_list_index(Request $request)
    {
        // 'pending'というクエリパラメータを取得。存在しない場合は'true'をデフォルト値とする
        $status = $request->query('pending', 'true');

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

        return view('admin_apply_list', [
            'applications' => $applications,
        ]);
    }


    public function admin_apply_judgement_index($attendance_correct_request_id)
    {
        // 申請IDからApplicationモデルのデータを取得
        $application = Application::with('user')->find($attendance_correct_request_id);

        // もし該当する申請データがなければ、エラーページなどにリダイレクト
        if (!$application) {
            return redirect()->back()->with('error', '申請が見つかりませんでした。');
        }

        // 複数回の休憩に対応するための配列
        $breakTimes = [];
        $maxBreaks = 5; // 最大休憩回数を設定。必要に応じて変更してください。

        // 休憩データをループで取得
        for ($i = 1; $i <= $maxBreaks; $i++) {
            $breakStartTimeField = "break_start_time_{$i}";
            $breakEndTimeField = "break_end_time_{$i}";

            if (isset($application->$breakStartTimeField) || isset($application->$breakEndTimeField)) {
                $breakTimes[] = [
                    'start_time' => $application->$breakStartTimeField ? Carbon::parse($application->$breakStartTimeField)->format('H:i') : null,
                    'end_time' => $application->$breakEndTimeField ? Carbon::parse($application->$breakEndTimeField)->format('H:i') : null,
                ];
            }
        }

        // ビューに渡すデータを整理
        $data = [
            'name' => $application->user->name,
            'date' => Carbon::parse($application->checkin_date)->format('Y年m月d日'),
            'clock_in_time' => $application->clock_in_time ? Carbon::parse($application->clock_in_time)->format('H:i') : '-',
            'clock_out_time' => $application->clock_out_time ? Carbon::parse($application->clock_out_time)->format('H:i') : '-',
            'break_times' => $breakTimes,
            'reason' => $application->reason,
            'pending' => $application->pending, // pendingステータスを追加
            'application_id' => $application->id,
        ];

        // 整理したデータをadmin_apply_judgement.blade.phpに渡して表示
        return view('admin_apply_judgement', compact('data'));
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

        return redirect()->route('user.stamping.index');
    }

    /**
     * 退勤処理を実行します。
     */
    public function attendance_create()
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

        return redirect()->route('user.stamping.index');
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

        return redirect()->route('user.stamping.index');
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

        return redirect()->route('user.stamping.index');
    }


        /**
     * 勤怠データを保存（新規作成または更新）する
     * * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function application_create(Request $request)
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


    /**
     * 管理者用勤怠承認（修正）処理
     */
    public function admin_attendance_approve(Request $request)
    {
        // フォームから送信された勤怠IDと日付を取得
        $attendanceId = $request->input('attendance_id');
        $date = $request->input('checkin_date');
        $staffUserId = $request->input('user_id');
        // ★修正点1: 元のリダイレクト先を取得
        $redirectTo = $request->input('redirect_to');

        try {
            DB::beginTransaction();

            // 勤怠IDが存在すれば既存レコードを検索
            if ($attendanceId) {
                $attendance = Attendance::find($attendanceId);
                // IDがあってもレコードが見つからない場合はエラー
                if (!$attendance) {
                    throw new \Exception('指定された勤怠記録が見つかりませんでした。');
                }
            } else {
                // 新しい勤怠レコードのインスタンスを作成
                if (!$staffUserId) {
                    throw new \Exception('ユーザーIDが指定されていません。');
                }
                $attendance = new Attendance();
                $attendance->user_id = $staffUserId;
                $attendance->checkin_date = $date;
            }

            // フォームから送信されたデータを取得
            $checkinTime = trim($request->input('clock_in_time'));
            $checkoutTime = trim($request->input('clock_out_time'));
            $breakTimes = $request->input('break_times', []);
            $reason = trim($request->input('reason'));

            // 出勤・退勤時間を更新
            $attendance->clock_in_time = !empty($checkinTime) ? Carbon::parse($date . ' ' . $checkinTime) : null;
            $attendance->clock_out_time = !empty($checkoutTime) ? Carbon::parse($date . ' ' . $checkoutTime) : null;

            // 休憩時間の初期化
            for ($i = 1; $i <= 4; $i++) {
                $attendance->{'break_start_time_' . $i} = null;
                $attendance->{'break_end_time_' . $i} = null;
            }

            $totalBreakSeconds = 0;

            // 休憩時間を更新し、合計休憩時間（秒）を計算
            foreach ($breakTimes as $index => $breakTime) {
                if ($index >= 4) {
                    break;
                }

                $breakStartTime = trim($breakTime['start_time'] ?? '');
                $breakEndTime = trim($breakTime['end_time'] ?? '');

                if (!empty($breakStartTime) && !empty($breakEndTime)) {
                    $attendance->{'break_start_time_' . ($index + 1)} = Carbon::parse($date . ' ' . $breakStartTime);
                    $attendance->{'break_end_time_' . ($index + 1)} = Carbon::parse($date . ' ' . $breakEndTime);
                    $totalBreakSeconds += $attendance->{'break_end_time_' . ($index + 1)}->timestamp - $attendance->{'break_start_time_' . ($index + 1)}->timestamp;
                }
            }

            // 総労働時間（秒）を計算
            $totalWorkSeconds = 0;
            if ($attendance->clock_in_time && $attendance->clock_out_time) {
                $totalWorkSeconds = $attendance->clock_out_time->timestamp - $attendance->clock_in_time->timestamp;
            }

            // 最終的な労働時間と休憩時間を分単位で計算し、レコードに設定
            $finalWorkSeconds = max(0, $totalWorkSeconds - $totalBreakSeconds);
            $attendance->work_time = round($finalWorkSeconds / 60);
            $attendance->break_total_time = round($totalBreakSeconds / 60);
            $attendance->reason = $reason;

            // 勤怠レコードを保存して更新を確定
            $attendance->save();

            DB::commit();

            // ★修正点2: 元のページにリダイレクト
            return redirect($redirectTo)->with('success', '勤怠データを修正しました。');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('勤怠修正エラー: ' . $e->getMessage());

            return redirect()->back()->with('error', '勤怠データの修正中にエラーが発生しました。');
        }
    }


    public function admin_apply_attendance_approve(Request $request)
    {
        // リクエストからアプリケーションIDを取得
        $applicationId = $request->input('id');

        if (empty($applicationId)) {
            // IDがリクエストに含まれていない場合はエラーを返す
            return redirect()->route('admin.applications.index')->with('error', '承認する勤怠申請が指定されていません。');
        }

        try {
            // トランザクションを開始
            DB::beginTransaction();

            // 指定されたIDの勤怠申請レコードを検索
            $application = Application::findOrFail($applicationId);

            // 申請内容に基づいて、attendancesテーブルのレコードを更新または新規作成
            // ユーザーIDと日付で既存のレコードを探す
            $attendance = Attendance::firstOrNew([
                'user_id' => $application->user_id,
                'checkin_date' => $application->checkin_date,
            ]);

            // applicationsテーブルのデータをattendancesテーブルにコピー
            $attendance->clock_in_time = $application->clock_in_time;
            $attendance->clock_out_time = $application->clock_out_time;

            // --- ここから計算ロジックを追加 ---

            $totalWorkSeconds = 0;
            $totalBreakSeconds = 0;

            // 労働時間を計算
            // 退勤時間と出勤時間が両方存在する場合のみ計算
            if ($application->clock_in_time && $application->clock_out_time) {
                // Unixタイムスタンプを使用して総勤務時間（秒）を計算
                $totalWorkSeconds = strtotime($application->clock_out_time) - strtotime($application->clock_in_time);

                // すべての休憩時間を再計算
                for ($i = 1; $i <= 4; $i++) {
                    $start = $application->{'break_start_time_' . $i};
                    $end = $application->{'break_end_time_' . $i};

                    if ($start && $end) {
                        $totalBreakSeconds += strtotime($end) - strtotime($start);
                    }
                }
            }

            // 最終的な労働時間（秒）を計算し、マイナスにならないようにする
            $finalWorkSeconds = max(0, $totalWorkSeconds - $totalBreakSeconds);

            // 労働時間を分単位に変換して代入
            $attendance->work_time = round($finalWorkSeconds / 60);

            // 休憩時間を分単位に変換して代入
            $attendance->break_total_time = round($totalBreakSeconds / 60);

            // 休憩時間をループでコピー
            for ($i = 1; $i <= 4; $i++) {
                $attendance->{'break_start_time_' . $i} = $application->{'break_start_time_' . $i};
                $attendance->{'break_end_time_' . $i} = $application->{'break_end_time_' . $i};
            }

            // 勤怠レコードを保存
            $attendance->save();

            // applicationsテーブルの`pending`を`false`に更新
            // このアクションは、`applications`テーブルに`pending`というboolean型のカラムが存在することを前提としています。
            $application->update(['pending' => false]);

            // トランザクションをコミット
            DB::commit();

            // 成功メッセージと共にリダイレクト
            return redirect()->route('apply.list')->with('success', '勤怠申請を承認しました。');

        } catch (\Exception $e) {
            // エラーが発生した場合はトランザクションをロールバック
            DB::rollBack();
            Log::error('勤怠承認エラー: ' . $e->getMessage());

            // エラーメッセージと共にリダイレクト
        return redirect()->route('apply.list')->with('error', '勤怠承認中にエラーが発生しました。');
        }
    }


    /**
     * 指定されたユーザー、年、月の勤怠データをCSV形式でダウンロードする
     *
     * @param Request $request POSTリクエストで送信された user_id, year, month を含む
     * @return StreamedResponse CSVファイルダウンロードレスポンス
     */
    public function export(Request $request)
    {
        // 1. リクエストからパラメータを取得
        $userId = $request->input('user_id');
        $year = $request->input('year');
        $month = $request->input('month');

        // 必須パラメータの確認
        if (empty($userId) || empty($year) || empty($month)) {
            return redirect()->back()->with('error', 'CSV出力に必要な情報が不足しています。');
        }

        // 2. 期間の設定とデータ取得
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // ユーザー名を取得（ファイル名やCSV内容に使用）
        $user = User::find($userId);
        $userName = $user ? $user->name : 'UnknownUser';

        // 勤怠データを取得（画面表示時と同じロジックを使用）
        $attendances = Attendance::where('user_id', $userId)
            ->whereDate('checkin_date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('checkin_date', '<=', $endDate->format('Y-m-d'))
            ->orderBy('checkin_date', 'asc')
            ->get();

        // 3. CSV生成ロジック
        $fileName = $userName . '_勤怠_' . $year . '年' . $month . '月.csv';
        
        // ----------------------------------------------------
        // 分単位の時間を HH:MM 形式に変換するヘルパー関数
        $formatMinutes = function ($minutes) {
            if (!is_numeric($minutes) || $minutes <= 0) return '0:00';
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return $hours . ':' . str_pad($remainingMinutes, 2, '0', STR_PAD_LEFT);
        };
        // ----------------------------------------------------

        // StreamedResponseでCSVダウンロードをストリーム配信
        $response = new StreamedResponse(function () use ($userName, $year, $month, $attendances, $formatMinutes) {
            $stream = fopen('php://output', 'w');

            // Excelなどの文字化け対策（BOMを付与）
            fwrite($stream, "\xEF\xBB\xBF");

            // ヘッダー行
            $header = [
                'ユーザー名',
                '日付',
                '曜日',
                '出勤時刻',
                '退勤時刻',
                '休憩時間(H:i)',
                '労働時間(H:i)'
            ];
            fputcsv($stream, $header);

            // データ行 (月のすべての日を出力)
            $currentDate = Carbon::create($year, $month, 1);
            $daysInMonth = $currentDate->daysInMonth;
            $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];

            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = Carbon::create($year, $month, $i);
                $dayOfWeek = $dayOfWeekMap[$date->dayOfWeek];
                $attendance = $attendances->firstWhere('checkin_date', $date->format('Y-m-d'));

                if ($attendance) {
                    $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;

                    $row = [
                        $userName,
                        $date->format('Y-m-d'),
                        $dayOfWeek,
                        $attendance->clock_in_time ? Carbon::parse($attendance->clock_in_time)->format('H:i') : '',
                        $hasClockedOut ? Carbon::parse($attendance->clock_out_time)->format('H:i') : '',
                        $formatMinutes($attendance->break_total_time ?? 0),
                        $formatMinutes($attendance->work_time ?? 0),
                    ];
                } else {
                    // 勤怠記録がない日
                    $row = [
                        $userName,
                        $date->format('Y-m-d'),
                        $dayOfWeek,
                        '-', '-', '0:00', '0:00'
                    ];
                }
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, 200, [
            // ダウンロード時のファイル名を指定
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);

        return $response;
    }
}