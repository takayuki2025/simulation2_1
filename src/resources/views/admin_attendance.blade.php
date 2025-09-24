@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_attendance.css') }}">
@endsection

@section('content')


    @php
        // URLパラメータから日付を取得、なければ今日の日付を使用
        $date = request()->get('date', date('Y-m-d'));
        $currentDate = \Carbon\Carbon::parse($date);

        // 前日と次日のURLを生成
        $prevDay = $currentDate->copy()->subDay()->format('Y-m-d');
        $nextDay = $currentDate->copy()->addDay()->format('Y-m-d');
    @endphp

    <div class="container">
        <div class="title">
            <!-- タイトルを動的に表示 -->
            <h2 class="tile_1">{{ $currentDate->format('Y年m月d日') }}の勤怠</h2>
        </div>
        <!-- 日付ナビゲーション -->
        <div class="date-navigation-frame">
            <div class="header1">
                <div class="navigation">
                    <a href="?date={{ $prevDay }}">前日</a>
                </div>
                <h2>
                    📅 <span id="current-date-display">{{ $currentDate->format('Y年m月d日') }}</span>
                </h2>
                <div class="navigation">
                    <a href="?date={{ $nextDay }}">次日</a>
                </div>
            </div>
        </div>

        <!-- 勤怠テーブル -->
        <div class="attendance-table-frame">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendances as $attendance)
                        @php
                            // ユーザーの勤怠情報が存在しない場合、表示しない
                            if ($attendance->user === null) {
                                continue;
                            }
                            // 退勤時間が記録されているか、かつ出勤時間と同じ値ではないかチェック
                            $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;
                        @endphp
                        <tr>
                            <td>{{ $attendance->user->name }}</td>
                            <td>{{ \Carbon\Carbon::parse($attendance->clock_in_time)->format('H:i') }}</td>
                            <!-- 退勤していない場合は何も表示しない -->
                            <td>{{ $hasClockedOut ? \Carbon\Carbon::parse($attendance->clock_out_time)->format('H:i') : '' }}</td>
                            <!-- 休憩時間が0ではない場合のみ表示 -->
                            <td>{{ $attendance->break_total_time > 0 ? floor($attendance->break_total_time / 60) . ':' . str_pad($attendance->break_total_time % 60, 2, '0', STR_PAD_LEFT) : '' }}</td>
                            <!-- 合計時間が0ではない場合のみ表示 -->
                            <td>{{ $attendance->work_time > 0 ? floor($attendance->work_time / 60) . ':' . str_pad($attendance->work_time % 60, 2, '0', STR_PAD_LEFT) : '' }}</td>
                            <td><a href="/attendance/detail/{{ $attendance->id }}" class="detail-button">詳細</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection