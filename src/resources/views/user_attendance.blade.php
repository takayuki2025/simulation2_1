@extends('layouts.user')

@section('content')

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0 auto;
            padding: 20px;
            width: 1400px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .date-navigation-frame {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .header1 {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header1 h2 {
            margin: 0;
            font-size: 1.5em;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header1 .navigation {
            display: flex;
            gap: 10px;
        }
        .header1 .navigation a {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .header1 .navigation a:hover {
            background-color: #0056b3;
        }
        .attendance-table-frame {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        .attendance-table th {
            padding: 12px;
            text-align: center;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #ccc;
        }
        .attendance-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        .attendance-table tr:last-child td {
            border-bottom: none;
        }
        .day-column {
            white-space: nowrap;
        }
        .sunday {
            color: #e74c3c;
        }
        .saturday {
            color: #3498db;
        }
        .detail-button {
            background-color: #28a745;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .detail-button:hover {
            background-color: #218838;
        }
    </style>

<body>
    @php
        // URLパラメータから年と月を取得、なければ現在の日付を使用
        $year = request()->get('year', date('Y'));
        $month = request()->get('month', date('m'));
        $date = \Carbon\Carbon::create($year, $month, 1);

        // 前月と次月のURLを生成
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();
    @endphp

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
                                <td><a href="/attendance/detail/{{ $attendance->id }}" class="detail-button">詳細</a></td>
                            @else
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</body>

@endsection