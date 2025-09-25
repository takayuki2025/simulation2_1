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

    public function user_list_index()
    {
        // 認証済みユーザーを取得
        $user = Auth::user();

        // ユーザーの全勤怠レコードを新しい順に取得
        $attendances = Attendance::where('user_id', $user->id)
                            ->orderBy('checkin_date', 'desc')
                            ->get();

        // 勤怠データをビューに渡して表示
        return view('user_attendance', compact('attendances'));
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
}