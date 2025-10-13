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
use Illuminate\Support\Facades\Log; //大規模プロジェクトの時のため実装しています。
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendantManagerController extends Controller
{
    /**
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function admin_staff_daily_index(Request $request)
    {
        $dateString = $request->get('date', Carbon::now()->toDateString());
        $currentDate = Carbon::parse($dateString);

        $attendances = Attendance::where('checkin_date', $dateString)
            ->with('user')
            ->get()
            ->keyBy('user_id');

        $allStaffUsers = User::where('role', '!=', 'admin')
            ->get();

        $dailyAttendanceData = [];
        $formatTime = function (?int $minutes): string {

            if ($minutes === null) return '';

            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT);
        };

        foreach ($allStaffUsers as $user) {
            $attendance = $attendances->get($user->id);

            $hasAttendanceRecord = $attendance !== null;
            $hasClockedOut = $hasAttendanceRecord
                ? ($attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time)
                : false;

            if ($hasAttendanceRecord) {
                $dailyAttendanceData[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'clockInTime' => Carbon::parse($attendance->clock_in_time)->format('H:i'),
                    'clockOutTime' => $hasClockedOut
                                        ? Carbon::parse($attendance->clock_out_time)->format('H:i')
                                        : '',
                    'breakTimeDisplay' => $formatTime($attendance->break_total_time),
                    'workTimeDisplay' => $formatTime($attendance->work_time),
                    'dateString' => $dateString,
                ];
            } else {
                $dailyAttendanceData[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'clockInTime' => '',
                    'clockOutTime' => '',
                    'breakTimeDisplay' => '',
                    'workTimeDisplay' => '',
                    'dateString' => $dateString,
                ];
            }
        }

        $hasAttendance = $allStaffUsers->isNotEmpty();
        $today = Carbon::now()->startOfDay();
        $viewData = [
            'currentDate' => $currentDate,
            'dailyAttendanceData' => $dailyAttendanceData,
            'hasAttendance' => $hasAttendance,
            'today' => $today,
        ];

        return view('admin-staff-daily-attendance', $viewData);
    }


    public function admin_user_attendance_detail_index(Request $request, $id = null)
    {
        $userId = $request->input('user_id') ?? $id;
        $date = $request->input('date') ?? Carbon::now()->toDateString();
        $staffUser = User::findOrFail($userId);
        $attendance = Attendance::where('user_id', $userId)
            ->where('checkin_date', $date)
            ->first();

        $application = Application::where('user_id', $userId)
            ->where('checkin_date', $date)
            ->first();

        $primaryData = null;

        if ($attendance && $application) {
            $attendanceUpdated = Carbon::parse($attendance->updated_at);
            $applicationUpdated = Carbon::parse($application->updated_at);

            if ($applicationUpdated->gt($attendanceUpdated)) {
                $primaryData = $application;
            } else {
                $primaryData = $attendance;
            }
        } elseif ($application) {
            $primaryData = $application;
        } elseif ($attendance) {
            $primaryData = $attendance;
        }

        $formBreakTimes = [];
        $breakTimeData = [];
        $hasClockTime = $primaryData && ($primaryData->clock_in_time || $primaryData->clock_out_time);

        if ($hasClockTime) {
            $breakTimeField = $primaryData->break_time;
            $breakTimeData = is_array($breakTimeField) ? $breakTimeField : json_decode($breakTimeField, true);
        }

        if (is_array($breakTimeData) && !empty($breakTimeData)) {
            foreach ($breakTimeData as $break) {
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

        $formBreakTimes[] = [
            'start_time' => '',
            'end_time' => ''
        ];

        $isPending = $application && $application->pending == true;

        $viewData = [
            'attendance' => $attendance,
            'user' => $staffUser,
            'date' => $date,
            'formBreakTimes' => $formBreakTimes,
            'application' => $application,
            'primaryData' => $primaryData,
            'isPending' => $isPending,
        ];

        return view('admin-attendance-detail', $viewData);
    }


    public function admin_staff_list_index(Request $request)
    {
        $users = User::all();

        return view('admin-staff-list', ['users' => $users,]);
    }


    /**
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  スタッフのユーザーID
     * @return \Illuminate\View\View
     */
    public function admin_staff_month_index(Request $request, $id)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $date = Carbon::create($year, $month, 1);
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();
        $staffUser = User::findOrFail($id);
        $attendances = Attendance::where('user_id', $id)
            ->whereYear('checkin_date', $year)
            ->whereMonth('checkin_date', $month)
            ->get();

        $daysInMonth = $date->daysInMonth;
        $monthlyAttendanceData = [];
        $dayOfWeekArray = ['日', '月', '火', '水', '木', '金', '土'];

        $formatTime = function (?int $minutes): string {
            if ($minutes === null) return '';

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
            $attendance = $attendances->firstWhere('checkin_date', $dateString);
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
                $dayData['clockInTime'] = Carbon::parse($attendance->clock_in_time)->format('H:i');
                $totalBreakMinutes = $attendance->break_total_time ?? 0;
                $dayData['breakTimeDisplay'] = $formatTime($totalBreakMinutes);
                $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;

                if ($hasClockedOut) {
                    $dayData['clockOutTime'] = Carbon::parse($attendance->clock_out_time)->format('H:i');
                    $dayData['workTimeDisplay'] = $formatTime($attendance->work_time);
                } else {
                    $dayData['clockOutTime'] = '';
                    $dayData['workTimeDisplay'] = '';
                }
            }

            $monthlyAttendanceData[] = $dayData;
        }

        $today = Carbon::now()->startOfDay();
        $viewData = [
            'date' => $date,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'staffUser' => $staffUser,
            'year' => $year,
            'month' => $month,
            'monthlyAttendanceData' => $monthlyAttendanceData,
            'today' => $today,
        ];

        return view('admin-staff-month-attendance', $viewData);
    }


    public function admin_apply_list_index(Request $request)
    {
        $status = $request->query('pending', 'true');
        $query = Application::query();

        if ($status === 'true') {
            $query->where('pending', true);
        } else {
            $query->where('pending', false);
        }

        $query->orderBy('checkin_date', 'asc');
        $applications = $query->get();

        return view('admin-apply-list', [
            'applications' => $applications,
        ]);
    }


    public function admin_apply_judgement_index($attendance_correct_request_id)
    {
        $application = Application::with('user')->find($attendance_correct_request_id);

        if (!$application) {
            return redirect()->back()->with('error', '申請が見つかりませんでした。');
        }

        $breakTimes = [];
        $breakTimeData = $application->break_time ?? [];

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
            'name' => $application->user->name,
            'date' => Carbon::parse($application->checkin_date)->format('Y年　　　　　 n月j日'),
            'clock_in_time' => $application->clock_in_time ? Carbon::parse($application->clock_in_time)->format('H:i') : '-',
            'clock_out_time' => $application->clock_out_time ? Carbon::parse($application->clock_out_time)->format('H:i') : '-',
            'break_times' => $breakTimes,
            'reason' => $application->reason,
            'pending' => $application->pending,
            'application_id' => $application->id,
        ];

        return view('admin-apply-judgement', compact('data'));
    }


    public function admin_attendance_approve(ApplicationAndAttendantRequest $request)
    {
        $attendanceId = $request->input('attendance_id');
        $date = $request->input('checkin_date');
        $staffUserId = $request->input('user_id');
        $redirectTo = $request->input('redirect_to');

        try {
            DB::beginTransaction();

            if ($attendanceId) {
                $attendance = Attendance::find($attendanceId);

                if (!$attendance) {
                    throw new \Exception('指定された勤怠記録が見つかりませんでした。');
                }
            } else {
                if (!$staffUserId) {
                    throw new \Exception('ユーザーIDが指定されていません。');
                }
                $attendance = new Attendance();
                $attendance->user_id = $staffUserId;
                $attendance->checkin_date = $date;
            }

            $checkinTime = trim($request->input('clock_in_time'));
            $checkoutTime = trim($request->input('clock_out_time'));
            $breakTimes = $request->input('break_times', []);
            $reason = trim($request->input('reason'));
            $clockInCarbon = !empty($checkinTime) ? Carbon::parse($date . ' ' . $checkinTime) : null;
            $clockOutCarbon = !empty($checkoutTime) ? Carbon::parse($date . ' ' . $checkoutTime) : null;
            $attendance->clock_in_time = $clockInCarbon;
            $attendance->clock_out_time = $clockOutCarbon;

            if ($clockInCarbon && $clockOutCarbon) {
                if ($clockOutCarbon->lt($clockInCarbon)) {
                    $attendance->clock_out_time = $clockOutCarbon->addDay();
                    $clockOutCarbon = $attendance->clock_out_time;
                }
            }

            $totalBreakSeconds = 0;
            $breakTimeJsonArray = [];

            foreach ($breakTimes as $breakTime) {
                $breakStartTime = trim($breakTime['start_time'] ?? '');
                $breakEndTime = trim($breakTime['end_time'] ?? '');

                if (!empty($breakStartTime) && !empty($breakEndTime)) {
                    $startCarbon = Carbon::parse($date . ' ' . $breakStartTime);
                    $endCarbon = Carbon::parse($date . ' ' . $breakEndTime);

                    if ($endCarbon->lt($startCarbon)) {
                        $endCarbon = $endCarbon->addDay();
                    }

                    if ($endCarbon->gt($startCarbon)) {
                        $breakTimeJsonArray[] = [
                            'start' => $startCarbon->toDateTimeString(),
                            'end' => $endCarbon->toDateTimeString(),
                        ];

                        $totalBreakSeconds += $endCarbon->timestamp - $startCarbon->timestamp;
                    }
                }
            }

            $attendance->break_time = $breakTimeJsonArray;
            $totalWorkSeconds = 0;
            if ($clockInCarbon && $clockOutCarbon) {

                if ($clockOutCarbon->gt($clockInCarbon)) {
                    $totalWorkSeconds = $clockOutCarbon->timestamp - $clockInCarbon->timestamp;
                }
            }

            $finalWorkSeconds = max(0, $totalWorkSeconds - $totalBreakSeconds);
            $attendance->work_time = round($finalWorkSeconds / 60);
            $attendance->break_total_time = round($totalBreakSeconds / 60);
            $attendance->reason = $reason;
            $attendance->save();

            DB::commit();

            return redirect($redirectTo)->with('success', '勤怠データを修正しました。');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('勤怠修正エラー: ' . $e->getMessage());

            return redirect()->back()->with('error', '勤怠データの修正中にエラーが発生しました。');
        }
    }


    public function admin_apply_attendance_approve(Request $request)
    {
        $applicationId = $request->input('id');

        if (empty($applicationId)) {
            return redirect()->route('admin.applications.index')->with('error', '承認する勤怠申請が指定されていません。');
        }

        try {
            DB::beginTransaction();

            $application = Application::findOrFail($applicationId);
            $checkinDate = $application->checkin_date; // 基準日

            $attendance = Attendance::firstOrNew([
                'user_id' => $application->user_id,
                'checkin_date' => $checkinDate,
            ]);

            $attendance->clock_in_time = $application->clock_in_time;
            $attendance->clock_out_time = $application->clock_out_time;
            $attendance->break_time = $application->break_time;
            $attendance->reason = $application->reason;
            $totalWorkSeconds = 0;
            $totalBreakSeconds = 0;
            $clockIn = $application->clock_in_time ? Carbon::parse($application->clock_in_time) : null;
            $clockOut = $application->clock_out_time ? Carbon::parse($application->clock_out_time) : null;

            if ($clockIn && $clockOut && $clockOut->gt($clockIn)) {
                $totalWorkSeconds = $clockOut->timestamp - $clockIn->timestamp;
            }

            $breakTimes = is_array($application->break_time) ? $application->break_time : json_decode($application->break_time, true) ?? [];

            foreach ($breakTimes as $break) {
                $start = $break['start'] ?? null;
                $end = $break['end'] ?? null;

                if ($start && $end) {
                    $breakStartCarbon = Carbon::parse($start);
                    $breakEndCarbon = Carbon::parse($end);

                    if ($breakEndCarbon->gt($breakStartCarbon)) {
                        $totalBreakSeconds += $breakEndCarbon->timestamp - $breakStartCarbon->timestamp;
                    }
                }
            }

            $finalWorkSeconds = max(0, $totalWorkSeconds - $totalBreakSeconds);
            $attendance->work_time = round($finalWorkSeconds / 60);
            $attendance->break_total_time = round($totalBreakSeconds / 60);
            $attendance->save();
            $application->update(['pending' => false]);
            DB::commit();

            return redirect()->route('apply.list')->with('success', '勤怠申請を承認しました。');

        } catch (\Exception $e) {

            DB::rollBack();
            Log::error('勤怠承認エラー: ' . $e->getMessage());

        return redirect()->route('apply.list')->with('error', '勤怠承認中にエラーが発生しました。');
        }
    }


    /**
     *
     * @param Request $request POSTリクエストで送信された user_id, year, month を含む
     * @return StreamedResponse CSVファイルダウンロードレスポンス
     */
    public function export(Request $request)
    {
        $userId = $request->input('user_id');
        $year = $request->input('year');
        $month = $request->input('month');

        if (empty($userId) || empty($year) || empty($month)) {
            return redirect()->back()->with('error', 'CSV出力に必要な情報が不足しています。');
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        $user = User::find($userId);
        $userName = $user ? $user->name : 'UnknownUser';
        $attendances = Attendance::where('user_id', $userId)
            ->whereDate('checkin_date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('checkin_date', '<=', $endDate->format('Y-m-d'))
            ->orderBy('checkin_date', 'asc')
            ->get();

        $fileName = $userName . '_勤怠_' . $year . '年' . $month . '月.csv';
        $formatMinutes = function ($minutes) {
            if (!is_numeric($minutes) || $minutes <= 0) return '0:00';
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return $hours . ':' . str_pad($remainingMinutes, 2, '0', STR_PAD_LEFT);
        };

        $response = new StreamedResponse(function () use ($userName, $year, $month, $attendances, $formatMinutes) {
            $stream = fopen('php://output', 'w');

            fwrite($stream, "\xEF\xBB\xBF");
            // 総計計算用の変数
            $totalWorkMinutes = 0;
            $totalBreakMinutes = 0;
            $totalOvertimeMinutes = 0;
            $totalWorkDays = 0;
            $totalScheduledWorkMinutes = 0;
            $legalWorkMinutes = 480;

            $header = [
                '日付',
                '曜日',
                '出勤時刻',
                '退勤時刻',
                '休憩時間(H:i)',
                '労働時間(H:i)',
                '所定労働時間(分)',
                '残業時間(分)',
                '申請/修正理由'
            ];
            fputcsv($stream, $header);

            $currentDate = Carbon::create($year, $month, 1);
            $daysInMonth = $currentDate->daysInMonth;
            $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];

            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = Carbon::create($year, $month, $i);
                $dayOfWeek = $dayOfWeekMap[$date->dayOfWeek];
                $attendance = $attendances->firstWhere('checkin_date', $date->format('Y-m-d'));

                if ($attendance) {
                    $breakMinutes = $attendance->break_total_time ?? 0;
                    $workMinutes = $attendance->work_time ?? 0;
                    $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;
                    $scheduledWorkMinutes = min($workMinutes, $legalWorkMinutes);
                    $overtimeMinutes = max(0, $workMinutes - $legalWorkMinutes);
                    $totalWorkMinutes += $workMinutes;
                    $totalBreakMinutes += $breakMinutes;
                    $totalScheduledWorkMinutes += $scheduledWorkMinutes;
                    $totalOvertimeMinutes += $overtimeMinutes;

                    if ($workMinutes > 0) {
                        $totalWorkDays++;
                    }

                    $row = [
                        $date->format('Y-m-d'),
                        $dayOfWeek,
                        $attendance->clock_in_time ? Carbon::parse($attendance->clock_in_time)->format('H:i') : '',
                        $hasClockedOut ? Carbon::parse($attendance->clock_out_time)->format('H:i') : '',
                        $formatMinutes($breakMinutes),
                        $formatMinutes($workMinutes),
                        $scheduledWorkMinutes,
                        $overtimeMinutes,
                        $attendance->reason ?? '',
                    ];
                } else {
                    $row = [
                        $date->format('Y-m-d'),
                        $dayOfWeek,
                        '-', '-', '0:00', '0:00', 0, 0, ''
                    ];
                }
                fputcsv($stream, $row);
            }

            $footer = [
                '月次総計（' . $userName . '）',
                '',
                '',
                '(月)出勤数: ' . $totalWorkDays . '日',
                $formatMinutes($totalBreakMinutes),
                $formatMinutes($totalWorkMinutes),
                $totalScheduledWorkMinutes,
                $totalOvertimeMinutes,
                '(総計)'
            ];
            fputcsv($stream, $footer);

            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);

        return $response;
    }
}
