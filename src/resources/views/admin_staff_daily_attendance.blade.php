@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/admin_staff_daily_attendance.css') }}">
<style>
/* データがない場合のメッセージのスタイル /
.no-attendance-message {
text-align: center;
padding: 40px 0;
margin-top: 20px;
background-color: #f7f7f7;
border: 1px solid #ddd;
border-radius: 8px;
font-size: 1.1em;
color: #555;
}
/ 勤怠テーブルのラッパー */
.attendance-table-frame {
width: 100%;
overflow-x: auto;
}
</style>
@endsection

@section('content')

@php
// URLパラメータから日付を取得、なければ今日の日付を使用
$date = request()->get('date', date('Y-m-d'));
$currentDate = \Carbon\Carbon::parse($date);
$user_attendances = []; // ユーザーごとの勤怠データを格納する配列を初期化
$hasAttendance = false; // 出勤データがあるユーザーが存在するかどうかのフラグ

// 勤怠データをユーザーごとに整理
foreach ($attendances as $attendance) {
    $user_attendances[$attendance->user_id] = $attendance;
}

// ユーザーリストをループして、出勤データを持つユーザーがいるかを確認
foreach ($users as $user) {
    if (isset($user_attendances[$user->id])) {
        $hasAttendance = true;
        break; // 誰か一人でもいればチェックを終了
    }
}

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
<a href="?date={{ $currentDate->copy()->subDay()->format('Y-m-d') }}">前日</a>
</div>
<h2>
📅 <span id="current-date-display">{{ $currentDate->format('Y年m月d日') }}</span>
</h2>
<div class="navigation">
<a href="?date={{ $currentDate->copy()->addDay()->format('Y-m-d') }}">次日</a>
</div>
</div>
</div>

<!-- 勤怠テーブル -->
<div class="attendance-table-frame">
    {{-- ★修正: 出勤データがある場合のみテーブルを表示 --}}
    @if ($hasAttendance)
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
            {{-- 全ユーザーをループして、出勤データがあるユーザーのみを表示 --}}
            @foreach ($users as $user)
                @php
                    $attendance = $user_attendances[$user->id] ?? null;
                @endphp

                {{-- 勤怠データ ($attendance) が存在する場合のみ表示する --}}
                @if ($attendance)
                    @php
                        $hasClockedOut = $attendance->clock_out_time !== null && $attendance->clock_out_time !== $attendance->clock_in_time;
                    @endphp
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>
                            {{ \Carbon\Carbon::parse($attendance->clock_in_time)->format('H:i') }}
                        </td>
                        <td>
                            @if ($hasClockedOut)
                                {{ \Carbon\Carbon::parse($attendance->clock_out_time)->format('H:i') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if ($attendance->break_total_time > 0)
                                {{ floor($attendance->break_total_time / 60) . ':' . str_pad($attendance->break_total_time % 60, 2, '0', STR_PAD_LEFT) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if ($attendance->work_time > 0)
                                {{ floor($attendance->work_time / 60) . ':' . str_pad($attendance->work_time % 60, 2, '0', STR_PAD_LEFT) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                        {{-- リダイレクト先のURLをクエリパラメータとして追加 --}}
                            <a href="{{ route('admin.user.attendance.detail.index', ['id' => $user->id, 'date' => $currentDate->format('Y-m-d'), 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    @else
    {{-- ★修正: 出勤データが一つもなかった場合のメッセージを表示 --}}
    <div class="no-attendance-message">
        <p>本日は出勤者のデータはありません。</p>
    </div>
    @endif
</div>

</div>

@endsection