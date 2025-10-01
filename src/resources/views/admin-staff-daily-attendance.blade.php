@extends('layouts.user-and-admin')

@section('css')

{{-- 修正: CSSファイル名をケバブケースに統一 --}}
<link rel="stylesheet" href="{{ asset('css/admin-staff-daily-attendance.css') }}">

@endsection

@section('content')

<div class="container">

<!-- タイトルを動的に表示 -->
{{-- 修正: tile_1 -> tile-1 --}}
<h2 class="page-title">{{ $currentDate->format('Y年m月d日') }}の勤怠</h2>

<!-- 日付ナビゲーション -->
<div class="date-navigation-frame">
{{-- 修正: header1 -> header-1 --}}
<div class="header-1">
<div class="navigation">
{{-- 修正: arrow_left -> arrow-left, navigation_arrow -> navigation-arrow --}}
<a href="?date={{ $currentDate->copy()->subDay()->format('Y-m-d') }}" class="arrow-left"><span class="navigation-arrow">← </span>前 日</a>
</div>
<h2>
📅 <span id="current-date-display">{{ $currentDate->format('Y年m月d日') }}</span>
</h2>
<div class="navigation">
{{-- 修正: arrow_right -> arrow-right, navigation_arrow -> navigation-arrow --}}
{{-- 制限を外し、常に翌日への移動を許可します --}}
<a href="?date={{ $currentDate->copy()->addDay()->format('Y-m-d') }}" class="arrow-right">翌 日<span class="navigation-arrow"> →</span></a>
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
{{-- 全スタッフが出勤データがなかった場合のメッセージ --}}
<div class="no-attendance-message">
<p>本日は出勤者のデータはありません。</p>
</div>
@endif
</div>

</div>

@endsection