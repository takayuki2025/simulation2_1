@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/admin_staff_month_attendance.css') }}">
@endsection

@section('content')

<body>

<div class="container">
<div class="title">
{{-- スタッフの名前を表示 --}}
<h2 class="tile_1">{{$staffUser->name}}さんの勤怠一覧</h2>
</div>
<!-- 日付ナビゲーション -->
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

<!-- 勤怠テーブル -->

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
{{-- コントローラーで準備した月次勤怠データ配列をループ --}}
@foreach ($monthlyAttendanceData as $dayData)
{{-- 土日クラスはコントローラーから渡されたフラグで設定 --}}
<tr class="{{ $dayData['isSunday'] ? 'sunday' : '' }} {{ $dayData['isSaturday'] ? 'saturday' : '' }}">
<td class="day-column">{{ $dayData['day'] }}日 ({{ $dayData['dayOfWeek'] }})</td>
{{-- 勤怠データがある場合 --}}
@if ($dayData['attendance'])
{{-- 出勤時間（すでにフォーマット済み） --}}
<td>{{ $dayData['clockInTime'] }}</td>
{{-- 退勤時間（すでにフォーマット済み） --}}
<td>{{ $dayData['clockOutTime'] }}</td>
{{-- 休憩時間（すでにフォーマット済み） --}}
<td>{{ $dayData['breakTimeDisplay'] }}</td>
{{-- 合計勤務時間（すでにフォーマット済み） --}}
<td>{{ $dayData['workTimeDisplay'] }}</td>
{{-- 詳細ボタン（勤怠データありの場合） --}}
<td>
<a href="{{ route('admin.user.attendance.detail.index', ['id' => $dayData['attendance']->user_id, 'date' => $dayData['dateString'], 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
</td>
@else
{{-- 勤怠データがない場合 --}}
<td>-</td>
<td>-</td>
<td>-</td>
<td>-</td>
{{-- 詳細ボタン（勤怠データなしの場合、スタッフIDを使用して詳細ページへ） --}}
<td>
<a href="{{ route('admin.user.attendance.detail.index', ['id' => $staffUser->id, 'date' => $dayData['dateString'], 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
</td>
@endif
</tr>
@endforeach
</tbody>
</table>
</div>

<div class="csv-area">
{{-- CSV出力用のフォーム --}}
<form action="{{ route('admin.staff.attendance.export') }}" method="POST" class="csv-button">
@csrf

{{-- ユーザーID、年、月を隠しフィールドで送信 --}}
<input type="hidden" name="user_id" value="{{ $staffUser->id }}">
<input type="hidden" name="year" value="{{ $year }}">
<input type="hidden" name="month" value="{{ $month }}">

<button type="submit" class="csv-submit">CSV出力</button>

</form>

</div>

</body>

@endsection