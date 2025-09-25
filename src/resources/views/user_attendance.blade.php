@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/user_attendance.css') }}">
@endsection

@section('content')

<body>
    <!-- @php
        // URLパラメータから年と月を取得、なければ現在の日付を使用
        $year = request()->get('year', date('Y'));
        $month = request()->get('month', date('m'));
        $date = \Carbon\Carbon::create($year, $month, 1);

        // 前月と次月のURLを生成
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();

        // ログインユーザーのIDを取得
        $userId = Auth::id();
    @endphp -->

    <div class="container">
        <div class="title">
            <h2 class="tile_1">勤怠一覧</h2>
        </div>
        <!-- 日付ナビゲーションを囲む新しい枠 -->
        <div class="date-navigation-frame">
            <div class="header1">
                <div class="navigation">
                    <a href="?year={{ $prevMonth->year }}&month={{ $prevMonth->month }}">前月</a>
                </div>
                <h2>
                    📅 <span id="current-date-display">{{ $date->format('Y年m月') }}</span>
                </h2>
                <div class="navigation">
                    <a href="?year={{ $nextMonth->year }}&month={{ $nextMonth->month }}">次月</a>
                </div>
            </div>
        </div>

        <!-- 勤怠テーブルを囲む新しい枠 -->
        <div class="attendance-table-frame">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // この月の全日をループ
                        $daysInMonth = $date->daysInMonth;
                    @endphp
                    @for ($i = 1; $i <= $daysInMonth; $i++)
                        @php
                            $currentDay = \Carbon\Carbon::create($year, $month, $i);
                            $attendance = $attendances->firstWhere('checkin_date', $currentDay->format('Y-m-d'));
                            $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$currentDay->dayOfWeek];
                        @endphp
                        <tr class="{{ $currentDay->dayOfWeek == 0 ? 'sunday' : '' }} {{ $currentDay->dayOfWeek == 6 ? 'saturday' : '' }}">
                            <td class="day-column">{{ $i }}日 ({{ $dayOfWeek }})</td>
                            @if ($attendance)
                                @php
                                    // 退勤時間が記録されているか、かつ出勤時間と同じ値ではないかチェック
                                    $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;
                                @endphp
                                <td>{{ \Carbon\Carbon::parse($attendance->clock_in_time)->format('H:i') }}</td>
                                <td>{{ $hasClockedOut ? \Carbon\Carbon::parse($attendance->clock_out_time)->format('H:i') : '' }}</td>
                                <td>{{ $hasClockedOut && $attendance->break_total_time > 0 ? floor($attendance->break_total_time / 60) . ':' . str_pad($attendance->break_total_time % 60, 2, '0', STR_PAD_LEFT) : '' }}</td>
                                <td>{{ $hasClockedOut && $attendance->work_time > 0 ? floor($attendance->work_time / 60) . ':' . str_pad($attendance->work_time % 60, 2, '0', STR_PAD_LEFT) : '' }}</td>
                                <td><a href="{{ route('user.attendance.detail.index', ['id' => $attendance->id]) }}" class="detail-button">詳細</a></td>
                            @else
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <!-- 勤怠データがない場合でも詳細ボタンを表示 -->
                                <td><a href="{{ route('user.attendance.detail.index', ['user_id' => $userId, 'date' => $currentDay->format('Y-m-d')]) }}" class="detail-button">詳細</a></td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</body>

@endsection