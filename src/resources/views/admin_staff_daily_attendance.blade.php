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
{{-- コントローラーで判定したフラグを使用 --}}
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
{{-- コントローラーで準備した勤怠データのみをループ --}}
@foreach ($dailyAttendanceData as $data)
<tr>
<td>{{ $data['user_name'] }}</td>
{{-- 時間はすべてコントローラーでフォーマット済み --}}
<td>{{ $data['clockInTime'] }}</td>
<td>{{ $data['clockOutTime'] }}</td>
<td>{{ $data['breakTimeDisplay'] }}</td>
<td>{{ $data['workTimeDisplay'] }}</td>
<td>
{{-- リダイレクト先のURLをクエリパラメータとして追加 --}}
<a href="{{ route('admin.user.attendance.detail.index', ['id' => $data['user_id'], 'date' => $data['dateString'], 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
</td>
</tr>
@endforeach
</tbody>
</table>
@else
{{-- 出勤データが一つもなかった場合のメッセージを表示 --}}
<div class="no-attendance-message">
<p>本日は出勤者のデータはありません。</p>
</div>
@endif
</div>

</div>

@endsection