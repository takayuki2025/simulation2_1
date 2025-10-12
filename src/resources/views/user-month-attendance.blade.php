@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/user-month-attendance.css') }}" />
@endsection

@section('content')
    <div class="container">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <h2 class="page-title">勤怠一覧</h2>

        <div class="date-nav-frame">
            <div class="calendar-title">
                <div class="nav">
                    {{-- prevMonthから年と月を取得してリンクを生成 --}}
                    <a href="?year={{ $prevMonth->year }}&month={{ $prevMonth->month }}" class="arrow-left">
                        <span class="nav-arrow">&#x2B05;</span>
                        前月
                    </a>
                </div>
                <h2>
                    📅
                    <span id="current-date-display">{{ $date->format('Y/m') }}</span>
                </h2>
                <div class="nav">
                    {{-- nextMonthから年と月を取得してリンクを生成 --}}
                    <a href="?year={{ $nextMonth->year }}&month={{ $nextMonth->month }}" class="arrow-right">
                        翌月
                        <span class="nav-arrow">&#x27A1;</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="attendance-table-frame">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th class="day-column-th">日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- コントローラで整形されたデータをループ --}}
                    @foreach ($formattedAttendanceData as $data)
                        {{-- 週末判定に基づいてクラスを適用 --}}
                        <tr class="{{ $data['is_weekend'] ? 'weekend' : '' }}">
                            <td class="day-column-td">{{ $data['day_label'] }}</td>
                            <td>{{ $data['clock_in'] }}</td>
                            <td>{{ $data['clock_out'] }}</td>
                            <td>{{ $data['break_time'] }}</td>
                            <td>{{ $data['work_time'] }}</td>
                            <td>
                                @if (\Carbon\Carbon::parse($data['date_key'])->lte($today))
                                    <a href="{{ $data['detail_url'] }}" class="detail-button">詳細</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
