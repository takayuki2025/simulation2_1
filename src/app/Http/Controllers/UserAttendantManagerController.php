<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use App\Http\Requests\ApplicationAndAttendantRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log; //大規模プロジェクトの時のため実装しています。

class UserAttendantManagerController extends Controller
{

    public function user_stamping_index()
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out_time')
            ->orderByDesc('checkin_date')
            ->first();

        if (!$attendance) {
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('checkin_date', Carbon::today())
                ->first();
        }

        $isClockedIn = isset($attendance) && isset($attendance->clock_in_time);
        $isClockedOut = isset($attendance) && isset($attendance->clock_out_time);

        $isBreaking = false;
        if (isset($attendance)) {
            $breakTimeData = is_array($attendance->break_time)
                ? $attendance->break_time
                : (is_string($attendance->break_time) ? json_decode($attendance->break_time, true) : null);

            if ($breakTimeData && is_array($breakTimeData) && !empty($breakTimeData)) {
                $lastBreak = end($breakTimeData);
                if (isset($lastBreak['start']) && empty($lastBreak['end'])) {
                    $isBreaking = true;
                }
            }
        }

        date_default_timezone_set('Asia/Tokyo');
        $now = Carbon::now();
        $currentDate = $now->format('Y年n月j日');
        $dayOfWeek = $now->dayOfWeek;
        $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];
        $currentDay = $dayOfWeekMap[$dayOfWeek];
        $currentTime = $now->format('H:i');

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


    public function user_month_index(Request $request)
    {
        $user = Auth::user();
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $date = Carbon::createFromDate($year, $month, 1);
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();
        $userId = Auth::id();
        $attendances = Attendance::where('user_id', $user->id)
            ->whereYear('checkin_date', $year)
            ->whereMonth('checkin_date', $month)
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->checkin_date)->format('Y-m-d');
            });

        $formattedAttendanceData = [];
        $daysInMonth = $date->daysInMonth;
        $today = Carbon::now()->startOfDay();
        $formattedMonth = $date->format('m');

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $currentDay = Carbon::createFromDate($year, $month, $i);
            $dateKey = $currentDay->format('Y-m-d');
            $attendance = $attendances->get($dateKey);
            $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];
            $dayOfWeek = $dayOfWeekMap[$currentDay->dayOfWeek];

            $data = [
                'day_label' => "{$formattedMonth}/{$currentDay->format('d')}({$dayOfWeek})",
                'is_weekend' => $currentDay->dayOfWeek == 0 || $currentDay->dayOfWeek == 6,
                'date_key' => $dateKey,
                'clock_in' => '',
                'clock_out' => '',
                'break_time' => '',
                'work_time' => '',
                'detail_url' => route('user.attendance.detail.index', ['date' => $dateKey]),
                'attendance_id' => null,
                'current_day_carbon' => $currentDay,
            ];

            if ($attendance) {
                $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;
                $data['clock_in'] = Carbon::parse($attendance->clock_in_time)->format('H:i');
                $data['clock_out'] = $hasClockedOut ? Carbon::parse($attendance->clock_out_time)->format('H:i') : '';

                if ($attendance->break_total_time !== null) {
                    $minutes = $attendance->break_total_time;
                    $data['break_time'] = floor($minutes / 60) . ':' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT);
                }

                if ($attendance->work_time !== null) {
                    $minutes = $attendance->work_time;
                    $data['work_time'] = floor($minutes / 60) . ':' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT);
                }

                $data['detail_url'] = route('user.attendance.detail.index', ['id' => $attendance->id, 'date' => $dateKey]);
                $data['attendance_id'] = $attendance->id;
            }

            $formattedAttendanceData[] = $data;
        }

        $viewData = [
            'formattedAttendanceData' => $formattedAttendanceData,
            'date' => $date,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'today' => $today,
        ];

        return view('user-month-attendance', $viewData);
    }


    public function user_attendance_detail_index(Request $request, $id = null)
    {
        $loggedInUser = Auth::user();
        $date = $request->input('date') ?? Carbon::now()->toDateString();
        $attendance = null;
        $targetUserId = $loggedInUser->id;

        if ($id) {
            $tempAttendance = Attendance::find($id);
            if ($tempAttendance && $tempAttendance->user_id == $loggedInUser->id) {
                $attendance = $tempAttendance;
                $date = Carbon::parse($attendance->checkin_date)->toDateString();
            } else {
                $attendance = Attendance::where('user_id', $loggedInUser->id)
                    ->whereDate('checkin_date', $date)
                    ->first();
            }
        } else {
            $attendance = Attendance::where('user_id', $loggedInUser->id)
                ->whereDate('checkin_date', $date)
                ->first();
        }

        $targetUser = $loggedInUser;
        $application = Application::where('user_id', $targetUser->id)
            ->whereDate('checkin_date', $date)
            ->first();

        if (!$application) {
            $prevDate = Carbon::parse($date)->subDay()->toDateString();
            $application = Application::where('user_id', $targetUser->id)
                ->whereDate('checkin_date', $prevDate)
                ->where('clock_out_time', '>', Carbon::parse($date)->startOfDay()->toDateTimeString())
                ->first();
        }

        $sourceData = $application ?? $attendance;

        $formBreakTimes = [];

        if ($sourceData && isset($sourceData->break_time)) {
            $breakTimes = is_array($sourceData->break_time) ? $sourceData->break_time : json_decode($sourceData->break_time, true);

            if (is_array($breakTimes)) {
                foreach ($breakTimes as $break) {
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
        }

        $formBreakTimes[] = [
            'start_time' => '',
            'end_time' => ''
        ];

        $breaksToDisplay = $formBreakTimes;
        if ($application && count($formBreakTimes) > 0) {
            $breaksToDisplay = array_slice($formBreakTimes, 0, count($formBreakTimes) - 1);
        }

        $hasNoBreakData = $application && count($breaksToDisplay) === 0;
    
        if ($hasNoBreakData) {
            $breaksToDisplay = [['start_time' => null, 'end_time' => null]];
        }
    
        $viewData = [
            'attendance' => $attendance,
            'user' => $targetUser,
            'date' => Carbon::parse($date)->toDateString(),
            'formBreakTimes' => $formBreakTimes,
            'breaksToDisplay' => $breaksToDisplay,
            'hasNoBreakData' => $hasNoBreakData,
            'application' => $application,
            'primaryData' => $sourceData,
        ];

        return view('user-attendance-detail', $viewData);
    }


    public function user_apply_index(Request $request)
    {
        $userId = Auth::id();
        $status = $request->query('pending', 'true');
        $query = Application::with('user')->where('user_id', $userId);

        if ($status === 'true') {
            $query->where('pending', true);
        } else {
            $query->where('pending', false);
        }

        $applications = $query->orderBy('checkin_date', 'asc')->get();

        $formattedApplications = $applications->map(function ($application) {
            $targetDate = null;
            $targetDateDisplay = '-';
            $detailUrl = '#';

            if ($application->checkin_date) {
                $carbonCheckinDate = Carbon::parse($application->checkin_date);
                $targetDate = $carbonCheckinDate->format('Y-m-d');
                $targetDateDisplay = $carbonCheckinDate->format('Y/m/d');
                $detailUrl = route('user.attendance.detail.index', ['date' => $targetDate]);
            }

            return [
                'id' => $application->id,
                'status_text' => $application->pending ? '承認待ち' : '承認済み',
                'user_name' => $application->user->name,
                'target_date_display' => $targetDateDisplay,
                'reason' => $application->reason,
                'created_at_display' => $application->created_at->format('Y/m/d'),
                'detail_url' => $detailUrl,
                'has_target_date' => (bool)$targetDate,
                'pending' => $application->pending,
            ];
        });

        return view('user-apply-list', [
            'applications' => $formattedApplications,
        ]);
    }


    public function clockIn()
    {
        $user = Auth::user();
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out_time')
            ->first();

        if (is_null($existingAttendance)) {
            Attendance::create([
                'user_id' => $user->id,
                'checkin_date' => Carbon::today(),
                'clock_in_time' => Carbon::now(),
            ]);
        } else {
            return redirect()->route('user.stamping.index')->with('error', '既に出勤中です。退勤処理を完了してください。');
        }

        return redirect()->route('user.stamping.index');
    }


    public function attendance_create()
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out_time')
            ->orderByDesc('checkin_date')
            ->first();

        if ($attendance) {
            $now = Carbon::now();
            $clockInCarbon = $attendance->clock_in_time ? Carbon::parse($attendance->clock_in_time) : null;

            if (!$clockInCarbon) {
                Log::warning('退勤処理エラー: ' . $user->id . 'の出勤時刻が見つかりません。');
                return redirect()->route('user.stamping.index')->with('error', '出勤時刻の記録がないため、退勤処理を完了できません。');
            }

            $breakTimes = is_array($attendance->break_time) ? $attendance->break_time : json_decode($attendance->break_time, true) ?? [];
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

            $totalWorkSeconds = 0;
            if ($now->gt($clockInCarbon)) {
                $totalWorkSeconds = $now->timestamp - $clockInCarbon->timestamp;
            }

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


    public function breakStart()
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out_time')
            ->orderByDesc('checkin_date')
            ->first();

        if ($attendance) {
            $breakTimes = is_array($attendance->break_time) ? $attendance->break_time : json_decode($attendance->break_time, true) ?? [];
            $lastBreak = end($breakTimes);

            if ($lastBreak && empty($lastBreak['end'])) {
            } else {
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


    public function breakEnd()
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out_time')
            ->orderByDesc('checkin_date')
            ->first();

        if ($attendance) {
            $breakTimes = is_array($attendance->break_time) ? $attendance->break_time : json_decode($attendance->break_time, true) ?? [];
            $updated = false;

            foreach (array_reverse($breakTimes, true) as $key => $break) {
                if (empty($break['end'])) {
                    $breakTimes[$key]['end'] = Carbon::now()->toDateTimeString();
                    $updated = true;
                    break;
                }
            }

            if ($updated) {
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

                $totalBreakMinutes = round($totalBreakSeconds / 60);
                $attendance->update([
                    'break_time' => $breakTimes,
                    'break_total_time' => $totalBreakMinutes,
                ]);
            }
        }

        return redirect()->route('user.stamping.index');
    }


    public function application_create(ApplicationAndAttendantRequest $request)
    {
        $user = Auth::user();
        $attendanceId = $request->input('attendance_id');
        $date = $request->input('checkin_date');
        $checkinTime = trim($request->input('clock_in_time'));
        $checkoutTime = trim($request->input('clock_out_time'));
        $reason = trim($request->input('reason'));
        $breakTimes = $request->input('break_times', []);
        $application = new Application();
        $application->user_id = $user->id;
        $application->checkin_date = $date;
        $application->pending = true;

        if ($attendanceId) {
            $application->attendance_id = $attendanceId;
        }

        $clockInCarbon = null;
        $clockOutCarbon = null;

        if (!empty($checkinTime)) {
            $clockInCarbon = Carbon::parse($date . ' ' . $checkinTime);
            $application->clock_in_time = $clockInCarbon;
        }

        if (!empty($checkoutTime)) {
            $clockOutCarbon = Carbon::parse($date . ' ' . $checkoutTime);

            if ($clockInCarbon && $clockOutCarbon->lt($clockInCarbon)) {
                $clockOutCarbon = $clockOutCarbon->addDay();
            }
            $application->clock_out_time = $clockOutCarbon;
        }

        $breakTimeJsonArray = [];
        foreach ($breakTimes as $breakTime) {
            $breakStartTime = trim($breakTime['start_time'] ?? '');
            $breakEndTime = trim($breakTime['end_time'] ?? '');

            if (!empty($breakStartTime) && !empty($breakEndTime)) {
                $breakStartCarbon = Carbon::parse($date . ' ' . $breakStartTime);
                $breakEndCarbon = Carbon::parse($date . ' ' . $breakEndTime);

                if ($breakEndCarbon->lt($breakStartCarbon)) {
                    $breakEndCarbon = $breakEndCarbon->addDay();
                }

                $breakTimeJsonArray[] = [
                    'start' => $breakStartCarbon->toDateTimeString(),
                    'end' => $breakEndCarbon->toDateTimeString(),
                ];
            }
        }

        $application->break_time = $breakTimeJsonArray;
        $application->reason = $reason;
        $application->save();
        $displayDate = Carbon::parse($date)->isoFormat('M月D日');
        $successMessage = "{$user->name}さん、{$displayDate}の修正申請を受け付けました。";

        return redirect()->route('user.month.index', ['date' => $date])->with('success', $successMessage);
    }
}
