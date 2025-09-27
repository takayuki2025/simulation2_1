@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/user_month_attendance.css') }}">
@endsection

@section('content')

<body>
<div class="container">
<div class="title">
<h2 class="tile_1">勤怠一覧</h2>
</div>

    <!-- 日付ナビゲーションを囲む新しい枠 -->
    <div class="date-navigation-frame">
        <div class="header1">
            <div class="navigation">
                {{-- prevMonthから年と月を取得してリンクを生成 --}}
                <a href="?year={{ $prevMonth->year }}&month={{ $prevMonth->month }}"><span class="arrow">←</span>前月</a>
            </div>
            <h2>
                📅 <span id="current-date-display">{{ $date->format('Y/m') }}</span>
            </h2>
            <div class="navigation">
                {{-- nextMonthから年と月を取得してリンクを生成 --}}
                <a href="?year={{ $nextMonth->year }}&month={{ $nextMonth->month }}">次月<span class="arrow">→</span></a>
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
                {{-- コントローラで整形されたデータをシンプルにループする --}}
                @foreach ($formattedAttendanceData as $data)
                    <tr class="{{ $data['is_weekend'] ? ($data['is_weekend'] === 0 ? 'sunday' : 'saturday') : '' }}">
                        <td class="day-column">{{ $data['day_label'] }}</td>
                        <td>{{ $data['clock_in'] }}</td>
                        <td>{{ $data['clock_out'] }}</td>
                        <td>{{ $data['break_time'] }}</td>
                        <td>{{ $data['work_time'] }}</td>
                        <td>
                            {{-- コントローラで適切なIDまたは日付付きのURLが生成されている --}}
                            <a href="{{ $data['detail_url'] }}" class="detail-button">詳細</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

</body>

@endsection