@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/admin_staff_daily_attendance.css') }}">
<style>
/* データがない場合のメッセージのスタイル */

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

/* 勤怠テーブルのラッパー */
.attendance-table-frame {
width: 100%;
overflow-x: auto;
}

/* リンクを無効化する場合のスタイル /
/ 今回の修正で「翌日」リンクの無効化（disabled-nav）は使用されなくなりますが、スタイルは残しておきます。 /
.disabled-nav {
color: #a0a0a0; / 薄い灰色 /
opacity: 0.7;
pointer-events: none; / クリックを無効化 */
cursor: default;
}

/* 詳細ボタンが無効な場合のスタイル */
.disabled-detail-button {
display: inline-block;
padding: 8px 12px;
border-radius: 4px;
background-color: #e0e0e0;
color: #888;
text-align: center;
cursor: default;
text-decoration: none;
line-height: 1;
}

</style>
@endsection

@section('content')

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
{{-- 制限を外し、常に翌日への移動を許可します --}}
<a href="?date={{ $currentDate->copy()->addDay()->format('Y-m-d') }}">翌日</a>
</div>
</div>
</div>

<!-- 勤怠テーブル -->

<div class="attendance-table-frame">
{{-- スタッフが一人でもいる場合はテーブルを表示する --}}
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
@foreach ($dailyAttendanceData as $data)
<tr>
<td>{{ $data['user_name'] }}</td>
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
{{-- 全スタッフが出勤データがなかった場合のメッセージ --}}
<div class="no-attendance-message">
<p>本日は出勤者のデータはありません。</p>
</div>
@endif
</div>

</div>

@endsection