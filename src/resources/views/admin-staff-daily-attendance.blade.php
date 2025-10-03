@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin-staff-daily-attendance.css') }}">
@endsection

@section('content')

<div class="container">

    <h2 class="page-title">{{ $currentDate->format('Y年m月d日') }}の勤怠</h2>

    <div class="date-nav-frame">
        <div class="calendar-title">
        <div class="nav">
{{-- 修正: arrow_left -> arrow-left, navigation_arrow -> navigation-arrow --}}
            <a href="?date={{ $currentDate->copy()->subDay()->format('Y-m-d') }}" class="arrow-left"><span class="nav-arrow">&#x2B05; </span>前 日</a>
        </div>
            <h2>📅 <span id="current-date-display">{{ $currentDate->format('Y年m月d日') }}</span></h2>
        <div class="nav">
{{-- 修正: arrow_right -> arrow-right, navigation_arrow -> navigation-arrow --}}
{{-- 制限を外し、常に翌日への移動を許可します --}}
            <a href="?date={{ $currentDate->copy()->addDay()->format('Y-m-d') }}" class="arrow-right">翌 日<span class="nav-arrow"> &#x27A1;</span></a>
        </div>
        </div>
    </div>

    <div class="attendance-table-frame">
        @if ($hasAttendance)
        <table class="attendance-table">
        <thead>
            <tr>
                <th>名 前</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($dailyAttendanceData as $data)
            <tr>
                <td class="daily-user-name">{{ $data['user_name'] }}</td>
                <td>{{ $data['clockInTime'] }}</td>
                <td>{{ $data['clockOutTime'] }}</td>
                <td>{{ $data['breakTimeDisplay'] }}</td>
                <td>{{ $data['workTimeDisplay'] }}</td>
                <td>
{{-- 現在の日付が今日の日付以前の場合（未来ではない場合）のみ「詳細」ボタンを表示 --}}
{{-- $currentDateが今日 ($today) と同じか過去の日付であれば true --}}
                    @if ($currentDate->lte($today))
{{-- リダイレクト先のURLをクエリパラメータとして追加 --}}
                        <a href="{{ route('admin.user.attendance.detail.index', ['id' => $data['user_id'], 'date' => $data['dateString'], 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
                    @else
{{-- 未来の日付の場合は非表示（空のセル）とする --}}
                        &nbsp;
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
        </table>
        @else

        @endif
    </div>
</div>

@endsection