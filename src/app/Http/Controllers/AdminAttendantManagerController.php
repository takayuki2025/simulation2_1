<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use App\Http\Requests\ApplicationAndAttendantRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // 大規模なプロジェクトの時のためLogファサードのインポートを追加
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendantManagerController extends Controller
{
    /**
     * 管理者用日次勤怠一覧を表示
     * (出勤データがないスタッフも一覧に含めます)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function admin_staff_daily_index(Request $request)
    {
        // URLパラメータから日付を取得、なければ今日の日付を使用
        $dateString = $request->get('date', Carbon::now()->toDateString());
        $currentDate = Carbon::parse($dateString);

        // 1. 指定された日付の全ユーザーの勤怠レコードを取得し、ユーザーIDでインデックス付け
        $attendances = Attendance::where('checkin_date', $dateString)
            ->with('user')
            ->get()
            ->keyBy('user_id'); // ユーザーIDをキーとしてアクセスしやすくする

        // 2. 全ての一般スタッフユーザーを取得 (管理者ユーザーを除外する想定)
        // ここではロールが'admin'ではないユーザーを取得すると仮定します。
        $allStaffUsers = User::where('role', '!=', 'admin')
            ->get();

        // **********************************************
        // 全ユーザーの勤怠データ準備ロジック
        // **********************************************

        $dailyAttendanceData = [];
        // 時間フォーマット用のヘルパー関数（例: 480分 -> 8:00, 0分 -> 0:00）
        $formatTime = function (?int $minutes): string {
            // ★修正点: 勤怠データがない場合に空文字列 '' を返す（打刻済みで0分の場合 0:00）
            if ($minutes === null) return '';

            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT);
        };

        // 3. 全スタッフをループし、勤怠データをマージ
        foreach ($allStaffUsers as $user) {
            // 当日の勤怠データがあるかチェック
            $attendance = $attendances->get($user->id);

            $hasAttendanceRecord = $attendance !== null;
            $hasClockedOut = $hasAttendanceRecord
                ? ($attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time)
                : false;

            if ($hasAttendanceRecord) {
                // 勤怠データがある場合
                $dailyAttendanceData[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'clockInTime' => Carbon::parse($attendance->clock_in_time)->format('H:i'),
                    'clockOutTime' => $hasClockedOut
                                        ? Carbon::parse($attendance->clock_out_time)->format('H:i')
                                        : '', // 退勤がない場合も空欄
                    // 勤怠データはあるが0分の場合、0:00が表示される (formatTime内のロジックで対応)
                    'breakTimeDisplay' => $formatTime($attendance->break_total_time),
                    'workTimeDisplay' => $formatTime($attendance->work_time),
                    'dateString' => $dateString,
                ];
            } else {
                // 勤怠データがない場合 (全て空欄 ' ' に設定)
                $dailyAttendanceData[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'clockInTime' => '',
                    'clockOutTime' => '',
                    // ★修正点: 勤怠データがない日は空欄 ''
                    'breakTimeDisplay' => '',
                    'workTimeDisplay' => '',
                    'dateString' => $dateString,
                ];
            }
        }

        // 勤怠データがあったかどうかのフラグを更新
        $hasAttendance = $allStaffUsers->isNotEmpty();
        // **********************************************
        // Bladeファイルで使用する今日の日付情報を追加
        // **********************************************
        $today = Carbon::now()->startOfDay();
        $viewData = [
            'currentDate' => $currentDate,
            'dailyAttendanceData' => $dailyAttendanceData,
            'hasAttendance' => $hasAttendance,
            'today' => $today, // 今日（システムの日付）をビューに渡す
        ];

        return view('admin-staff-daily-attendance', $viewData);
    }


    /**
     * 管理者向けユーザー勤怠詳細表示
     * 申請データが存在すればそれを優先してフォームに表示する
     */
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
        // フォームの初期値として使用するデータソースを決定
        // 申請データがあればそれを優先し、なければ既存の勤怠データを使用する
        $sourceData = $application ?? $attendance;
        // ----------------------------------------------------
        // 休憩時間のフォーム入力欄の準備 (JSON配列から作成)
        // ----------------------------------------------------
        $formBreakTimes = [];
        $breakTimeData = [];
        // ★最終修正: 以下の条件をすべて満たす場合のみ、休憩データを採用する
        // 1. $sourceData (申請または勤怠) が存在する
        // 2. 出勤時刻 または 退勤時刻 のいずれかが存在する
        $hasClockTime = $sourceData && ($sourceData->clock_in_time || $sourceData->clock_out_time);

        if ($hasClockTime) {
            // 出勤・退勤時刻が存在する場合のみ、break_timeのデータを取得
            // break_timeがJSON文字列であればデコードを試みる
            $breakTimeData = is_array($sourceData->break_time) ? $sourceData->break_time : json_decode($sourceData->break_time, true);
        }

        // 既存の休憩データをフォーム形式に整形
        if (is_array($breakTimeData) && !empty($breakTimeData)) {
            foreach ($breakTimeData as $break) {
                // 内部キーを 'start' と 'end' に変更
                $start = $break['start'] ?? null;
                $end = $break['end'] ?? null;

                if ($start || $end) {
                    $formBreakTimes[] = [
                        'start_time' => $start ? Carbon::parse($start)->format('H:i') : '',
                        'end_time' => $end ? Carbon::parse($end)->format('H:i') : ''
                    ];
                }
            }
        }

        // ----------------------------------------------------
        // ★ 修正箇所: 常に1つの空の休憩フォームを無条件に追加する
        // ----------------------------------------------------
        $formBreakTimes[] = [
            'start_time' => '',
            'end_time' => ''
        ];
        // ビューに渡すデータをまとめる
        $viewData = [
            'attendance' => $attendance,
            'user' => $staffUser,
            'date' => $date,
            'formBreakTimes' => $formBreakTimes, // 優先度に基づいて構築された休憩時間
            'application' => $application,
            'primaryData' => $sourceData, // フォームの主要なデータソース（申請データ優先）
        ];

        // 勤怠詳細データをビューに渡して表示
        return view('admin-attendance-detail', $viewData);
    }


        public function admin_staff_list_index(Request $request)
    {
        $users = User::all();

        return view('admin-staff-list', [
            'users' => $users,
        ]);
    }


    /**
     * 特定スタッフの月別勤怠一覧を表示する。
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  スタッフのユーザーID
     * @return \Illuminate\View\View
     */
    public function admin_staff_month_index(Request $request, $id)
    {
        // URLパラメータから年と月を取得、なければ現在の日付を使用
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $date = Carbon::create($year, $month, 1);
        // 前月と次月のURLを生成
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();
        // 指定されたIDのユーザーを1人だけ取得
        $staffUser = User::findOrFail($id);
        // 指定されたIDのユーザーとその月の勤怠レコードを取得
        $attendances = Attendance::where('user_id', $id)
            ->whereYear('checkin_date', $year)
            ->whereMonth('checkin_date', $month)
            ->get();
        // **********************************************
        // データ準備ロジック
        // **********************************************
        $daysInMonth = $date->daysInMonth;
        $monthlyAttendanceData = [];
        $dayOfWeekArray = ['日', '月', '火', '水', '木', '金', '土'];

        // 時間フォーマット用のヘルパー関数（例: 480分 -> 8:00, 0分 -> 0:00）
        $formatTime = function (?int $minutes): string {
            // nullの場合は空文字列 '' を返す（未打刻対応）
            if ($minutes === null) return '';

            // 0分以下の場合、'0:00' を返す（出勤直後の休憩0分対応、および0分対応）
            if ($minutes <= 0) {
                return '0:00';
            }

            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT);
        };

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $currentDay = Carbon::create($year, $month, $i);
            $dateString = $currentDay->format('Y-m-d');
            // その日の勤怠データを取得
            $attendance = $attendances->firstWhere('checkin_date', $dateString);
            // 勤怠データがない日の初期値はすべて空欄にする
            $dayData = [
                'day' => $i,
                'dayOfWeek' => $dayOfWeekArray[$currentDay->dayOfWeek],
                'isSunday' => $currentDay->dayOfWeek === 0,
                'isSaturday' => $currentDay->dayOfWeek === 6,
                'dateString' => $dateString,
                'attendance' => $attendance,
                'clockInTime' => '',
                'clockOutTime' => '',
                'breakTimeDisplay' => '',
                'workTimeDisplay' => '',
            ];

            if ($attendance) {
                // 出勤時間のフォーマット
                $dayData['clockInTime'] = Carbon::parse($attendance->clock_in_time)->format('H:i');

                // 休憩時間表示のロジックを修正:
                // ★修正点1: 出勤打刻があれば、退勤打刻の有無にかかわらず、現在の休憩合計時間を表示する。
                // break_total_time が null の場合は 0 として扱い、'0:00' が表示されるようにする。
                $totalBreakMinutes = $attendance->break_total_time ?? 0;
                $dayData['breakTimeDisplay'] = $formatTime($totalBreakMinutes);
                // 退勤時間が記録されているか、かつ出勤時間と同じ値ではないかチェック
                $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;

                if ($hasClockedOut) {
                    // 退勤時間のフォーマット
                    $dayData['clockOutTime'] = Carbon::parse($attendance->clock_out_time)->format('H:i');
                    // 合計勤務時間 (退勤打刻があれば表示)
                    $dayData['workTimeDisplay'] = $formatTime($attendance->work_time);
                } else {
                    // 退勤がない場合は、退勤時間と合計勤務時間を空欄にする
                    $dayData['clockOutTime'] = '';
                    $dayData['workTimeDisplay'] = '';
                }
            }

            $monthlyAttendanceData[] = $dayData;
        }

        // 今日の日付を取得 (比較に使用)
        $today = Carbon::now()->startOfDay();
        $viewData = [
            'date' => $date,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'staffUser' => $staffUser,
            'year' => $year,
            'month' => $month,
            // 新しい準備済みデータ配列
            'monthlyAttendanceData' => $monthlyAttendanceData,
            'today' => $today, // 今日（システムの日付）をビューに渡す
        ];

        return view('admin-staff-month-attendance', $viewData);
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

                // 💡 修正点: 対象日時（checkin_date）を基準に昇順（古い順）でソート
        $query->orderBy('checkin_date', 'asc');
        // フィルタリングされた結果を取得
        $applications = $query->get();

        return view('admin-apply-list', [
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

        // ----------------------------------------------------
        // 休憩時間の準備 (JSONカラム 'break_time' から取得)
        // ----------------------------------------------------
        $breakTimes = [];
        // JSONカラム 'break_time' から休憩データを取得
        // Applicationモデルで break_time が配列としてキャストされていることを想定
        $breakTimeData = $application->break_time ?? [];

        // 既存の休憩データをフォーム形式に整形
        if (is_array($breakTimeData) && !empty($breakTimeData)) {
            foreach ($breakTimeData as $break) {
                // 内部キー 'start' と 'end' を使用
                $start = $break['start'] ?? null;
                $end = $break['end'] ?? null;

                // startまたはendが存在する場合のみ配列に追加
                if ($start || $end) {
                    $breakTimes[] = [
                        'start_time' => $start ? Carbon::parse($start)->format('H:i') : null,
                        'end_time' => $end ? Carbon::parse($end)->format('H:i') : null,
                    ];
                }
            }
        }

        // ビューに渡すデータを整理
        $data = [
            'name' => $application->user->name,
            // 修正箇所: 'Y年' の後に半角スペースを追加 -> 'Y年 m月d日'
            'date' => Carbon::parse($application->checkin_date)->format('Y年　　　　n月j日'),
            'clock_in_time' => $application->clock_in_time ? Carbon::parse($application->clock_in_time)->format('H:i') : '-',
            'clock_out_time' => $application->clock_out_time ? Carbon::parse($application->clock_out_time)->format('H:i') : '-',
            'break_times' => $breakTimes, // JSONから整形された休憩データ
            'reason' => $application->reason,
            'pending' => $application->pending, // pendingステータスを追加
            'application_id' => $application->id,
        ];

        // 整理したデータをadmin_apply_judgement.blade.phpに渡して表示
        return view('admin-apply-judgement', compact('data'));
    }

    /**
     * 管理者による手動での勤怠データ修正・承認処理
     * 日跨ぎ、休憩時間の合計計算ロジックを修正済み
     */
    public function admin_attendance_approve(ApplicationAndAttendantRequest $request)
    {
        // フォームから送信された勤怠IDと日付を取得
        $attendanceId = $request->input('attendance_id');
        $date = $request->input('checkin_date');
        $staffUserId = $request->input('user_id');
        // 元のリダイレクト先を取得
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
            // 出勤・退勤時間を設定 (Carbonインスタンスを作成)
            $clockInCarbon = !empty($checkinTime) ? Carbon::parse($date . ' ' . $checkinTime) : null;
            $clockOutCarbon = !empty($checkoutTime) ? Carbon::parse($date . ' ' . $checkoutTime) : null;
            $attendance->clock_in_time = $clockInCarbon;
            $attendance->clock_out_time = $clockOutCarbon;

            // 💡 修正点1: 退勤時間が出勤時間より前の場合、日付を翌日に補正 (日跨ぎ対応)
            if ($clockInCarbon && $clockOutCarbon) {
                // 退勤時刻が出勤時刻よりも前の日付・時刻になっていたら
                if ($clockOutCarbon->lt($clockInCarbon)) {
                    // 退勤時刻の日付を翌日に設定
                    $attendance->clock_out_time = $clockOutCarbon->addDay();
                    $clockOutCarbon = $attendance->clock_out_time; // 補正後の値を参照
                }
            }

            // --- 修正箇所2: 休憩時間をJSON形式に変換し、合計時間を計算 ---
            $totalBreakSeconds = 0;
            $breakTimeJsonArray = [];

            foreach ($breakTimes as $breakTime) {
                $breakStartTime = trim($breakTime['start_time'] ?? '');
                $breakEndTime = trim($breakTime['end_time'] ?? '');

                if (!empty($breakStartTime) && !empty($breakEndTime)) {
                    $startCarbon = Carbon::parse($date . ' ' . $breakStartTime);
                    $endCarbon = Carbon::parse($date . ' ' . $breakEndTime);

                    // 休憩終了が出勤日の休憩開始より前なら、翌日として補正 (休憩での日跨ぎ対応)
                    if ($endCarbon->lt($startCarbon)) {
                        $endCarbon = $endCarbon->addDay();
                    }

                    // 休憩終了が休憩開始よりも後であることを確認してから計算
                    if ($endCarbon->gt($startCarbon)) {
                        // JSON配列に追加
                        $breakTimeJsonArray[] = [
                            'start' => $startCarbon->toDateTimeString(),
                            'end' => $endCarbon->toDateTimeString(),
                        ];

                        // 合計休憩時間（秒）を計算
                        $totalBreakSeconds += $endCarbon->timestamp - $startCarbon->timestamp;
                    }
                }
            }

            // break_time JSONカラムに設定
            $attendance->break_time = $breakTimeJsonArray;
            // --- 修正箇所2: 終了 ---
            // 総労働時間（秒）を計算
            $totalWorkSeconds = 0;
            if ($clockInCarbon && $clockOutCarbon) {
                // 計算前に、退勤が出勤より後であることを確認 (順序が正しい場合のみ計算)
                if ($clockOutCarbon->gt($clockInCarbon)) {
                    $totalWorkSeconds = $clockOutCarbon->timestamp - $clockInCarbon->timestamp;
                }
            }

            // 最終的な労働時間と休憩時間を分単位で計算し、レコードに設定
            $finalWorkSeconds = max(0, $totalWorkSeconds - $totalBreakSeconds);
            $attendance->work_time = round($finalWorkSeconds / 60);
            $attendance->break_total_time = round($totalBreakSeconds / 60);
            $attendance->reason = $reason;
            // 勤怠レコードを保存して更新を確定
            $attendance->save();

            DB::commit();

            // 元のページにリダイレクト
            return redirect($redirectTo)->with('success', '勤怠データを修正しました。');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('勤怠修正エラー: ' . $e->getMessage());

            return redirect()->back()->with('error', '勤怠データの修正中にエラーが発生しました。');
        }
    }


    /**
     * 勤怠申請を承認し、attendancesテーブルを更新します。
     * JSON休憩と日跨ぎに対応。
     */
    public function admin_apply_attendance_approve(Request $request)
    {
        // リクエストからアプリケーションIDを取得
        $applicationId = $request->input('id');

        if (empty($applicationId)) {
            return redirect()->route('admin.applications.index')->with('error', '承認する勤怠申請が指定されていません。');
        }

        try {
            DB::beginTransaction();

            // 指定されたIDの勤怠申請レコードを検索
            $application = Application::findOrFail($applicationId);
            $checkinDate = $application->checkin_date; // 基準日

            // 申請内容に基づいて、attendancesテーブルのレコードを更新または新規作成
            $attendance = Attendance::firstOrNew([
                'user_id' => $application->user_id,
                'checkin_date' => $checkinDate,
            ]);

            // applicationsテーブルのデータをattendancesテーブルにコピー
            $attendance->clock_in_time = $application->clock_in_time;
            $attendance->clock_out_time = $application->clock_out_time;
            $attendance->break_time = $application->break_time; // JSONカラムをそのままコピー
            $attendance->reason = $application->reason;
            $totalWorkSeconds = 0;
            $totalBreakSeconds = 0;
            // --- 修正箇所: Carbonを使って労働時間と休憩時間を正確に計算 ---
            $clockIn = $application->clock_in_time ? Carbon::parse($application->clock_in_time) : null;
            $clockOut = $application->clock_out_time ? Carbon::parse($application->clock_out_time) : null;

            // 労働時間を計算 (日跨ぎは申請作成時にCarbonで処理されている前提)
            if ($clockIn && $clockOut && $clockOut->gt($clockIn)) {
                $totalWorkSeconds = $clockOut->timestamp - $clockIn->timestamp;
            }

            // 休憩時間を break_time JSONカラムから計算
            // $application->break_timeが既に配列の場合はそのまま、文字列の場合はデコード
            $breakTimes = is_array($application->break_time) ? $application->break_time : json_decode($application->break_time, true) ?? [];

            foreach ($breakTimes as $break) {
                $start = $break['start'] ?? null;
                $end = $break['end'] ?? null;

                if ($start && $end) {
                    $breakStartCarbon = Carbon::parse($start);
                    $breakEndCarbon = Carbon::parse($end);

                    // 休憩終了が開始より後であることを確認 (申請作成時に日跨ぎ補正済みのはずだが念のため)
                    if ($breakEndCarbon->gt($breakStartCarbon)) {
                        $totalBreakSeconds += $breakEndCarbon->timestamp - $breakStartCarbon->timestamp;
                    }
                }
            }
            // --- 修正箇所終了 ---
            // 最終的な労働時間（秒）を計算し、マイナスにならないようにする
            $finalWorkSeconds = max(0, $totalWorkSeconds - $totalBreakSeconds);
            // 労働時間を分単位に変換して代入
            $attendance->work_time = round($finalWorkSeconds / 60);
            // 休憩時間を分単位に変換して代入
            $attendance->break_total_time = round($totalBreakSeconds / 60);
            // 勤怠レコードを保存
            $attendance->save();
            // applicationsテーブルの`pending`を`false`に更新
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
