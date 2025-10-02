@extends('layouts.user-and-admin')

@section('css')

{{-- 修正: CSSファイル名をケバブケースに統一 --}}
<link rel="stylesheet" href="{{ asset('css/admin-staff-month-attendance.css') }}">
@endsection

@section('content')



<div class="container">

{{-- スタッフの名前を表示 --}}
{{-- 修正: tile_1 -> tile-1 --}}
<h2 class="page-title">{{$staffUser->name}}さんの勤怠一覧</h2>

<!-- 日付ナビゲーション -->
<div class="date-nav-frame">
{{-- 修正: header1 -> header-1 --}}
<div class="calendar-title">
<div class="nav">
{{-- 修正: arrow_left -> arrow-left, navigation_arrow -> navigation-arrow --}}
<a href="?year={{ $prevMonth->year }}&month={{ $prevMonth->month }}" class="arrow-left"><span class="nav-arrow">← </span>前 月</a>
</div>
<h2>
📅 <span id="current-date-display">{{ $date->format('Y/m') }}</span>
</h2>
<div class="nav">
{{-- 修正: arrow_right -> arrow-right, navigation_arrow -> navigation-arrow --}}
{{-- 次月への移動は常に許可 --}}
<a href="?year={{ $nextMonth->year }}&month={{ $nextMonth->month }}" class="arrow-right">翌 月<span class="nav-arrow"> →</span></a>
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
<td class="day-column">
    {{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ str_pad($dayData['day'], 2, '0', STR_PAD_LEFT) }}({{ $dayData['dayOfWeek'] }})
</td>
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
{{-- ★未来の日付ではない場合（今日以前）のみ詳細ボタンを表示 --}}
@if (\Carbon\Carbon::parse($dayData['dateString'])->lte($today))
<a href="{{ route('admin.user.attendance.detail.index', ['id' => $dayData['attendance']->user_id, 'date' => $dayData['dateString'], 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
@else
&nbsp; {{-- 未来の場合は空欄 --}}
@endif
</td>
@else
{{-- 勤怠データがない場合 --}}
<td></td>
<td></td>
<td></td>
<td></td>
{{-- 詳細ボタン（勤怠データなしの場合、スタッフIDを使用して詳細ページへ） --}}
<td>
{{-- ★未来の日付ではない場合（今日以前）のみ詳細ボタンを表示 --}}
@if (\Carbon\Carbon::parse($dayData['dateString'])->lte($today))
<a href="{{ route('admin.user.attendance.detail.index', ['id' => $staffUser->id, 'date' => $dayData['dateString'], 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
@else
&nbsp; {{-- 未来の場合は空欄 --}}
@endif
</td>
@endif
</tr>
@endforeach
</tbody>
</table>
</div>

<div class="csv-area">
{{-- CSV出力用のフォーム (クラス名 csv-submit から csv-button に変更) --}}
<form action="{{ route('admin.staff.attendance.export') }}" method="POST" class="csv-button">
@csrf

{{-- ユーザーID、年、月を隠しフィールドで送信 --}}
<input type="hidden" name="user_id" value="{{ $staffUser->id }}">
<input type="hidden" name="year" value="{{ $year }}">
<input type="hidden" name="month" value="{{ $month }}">

{{-- ボタン自体にはクラス名を付与せず、フォーム（.csv-button）の子要素としてCSSを適用 --}}
<button type="submit">CSV出力</button>

</form>

</div>



@endsection