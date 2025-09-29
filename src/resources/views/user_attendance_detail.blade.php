@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/user_attendance_detail.css') }}">
@endsection

@section('content')

<body>
<div class="container">

<div class="title">
<h2 class="tile_1">勤怠詳細・修正申請</h2>
</div>

<div class="attendance-detail-frame">
<form action="{{ route('application.create') }}" method="POST" id="attendance-form">
@csrf
<!-- 勤怠データIDが存在する場合、IDを渡す -->
@if($attendance)
<input type="hidden" name="attendance_id" value="{{ $attendance->id }}">
@endif
<input type="hidden" name="user_id" value="{{ $user->id }}">
<input type="hidden" name="checkin_date" value="{{ \Carbon\Carbon::parse($date)->format('Y-m-d') }}">

{{-- checkin_date自体のエラー表示（隠しフィールドなので目立つように表示） --}}
@error('checkin_date')
<p class="error-message date-error">{{ $message }}</p>
@enderror

<input type="hidden" name="redirect_to" value="{{ request()->input('redirect_to') }}">

<table class="detail-table">
<tbody>
<tr>
<th>名前</th>
<td>
{{ $user->name }}
</td>
</tr>
<tr>
<th>日付</th>
<td>
{{ \Carbon\Carbon::parse($date)->format('Y年m月d日') }}
</td>
</tr>
<tr>
<th>出勤・退勤時間</th>
<td class="time-inputs">
{{-- 1. 出勤時刻ブロック --}}
<div class="input-block">
<input type="text" name="clock_in_time"
       {{-- 修正: old('clock_in_time')を最優先し、なければ$primaryDataの値を使う --}}
       value="{{ old('clock_in_time', $primaryData && $primaryData->clock_in_time ? \Carbon\Carbon::parse($primaryData->clock_in_time)->format('H:i') : '') }}"
       class="@error('clock_in_time') is-invalid @enderror">
@error('clock_in_time')
<span class="error-message">{{ $message }}</span>
@enderror
</div>

<span>〜</span>

{{-- 2. 退勤時刻ブロック --}}

<div class="input-block">
<input type="text" name="clock_out_time"
       {{-- 修正: old('clock_out_time')を最優先し、なければ$primaryDataの値を使う --}}
       value="{{ old('clock_out_time', $primaryData && $primaryData->clock_out_time ? \Carbon\Carbon::parse($primaryData->clock_out_time)->format('H:i') : '') }}"
       class="@error('clock_out_time') is-invalid @enderror">
@error('clock_out_time')
<span class="error-message">{{ $message }}</span>
@enderror
</div>

</td>
</tr>

{{-- break_times配列をPOST送信 --}}
@foreach($formBreakTimes as $index => $breakTime)

<tr>
<th>休憩{{ $index + 1 }}</th>
<td class="time-inputs">
{{-- 休憩開始時刻ブロック --}}
<div class="input-block">
    <input type="text" name="break_times[{{ $index }}][start_time]"
           {{-- 修正: old()でリクエストに残っている値を優先する --}}
           value="{{ old('break_times.' . $index . '.start_time', $breakTime['start_time'] ?? '') }}"
           class="@error('break_times.' . $index . '.start_time') is-invalid @enderror">
    @error('break_times.' . $index . '.start_time')
    <span class="error-message">{{ $message }}</span>
    @enderror
</div>

<span>〜</span>

{{-- 休憩終了時刻ブロック --}}

<div class="input-block">
    <input type="text" name="break_times[{{ $index }}][end_time]"
           {{-- 修正: old()でリクエストに残っている値を優先する --}}
           value="{{ old('break_times.' . $index . '.end_time', $breakTime['end_time'] ?? '') }}"
           class="@error('break_times.' . $index . '.end_time') is-invalid @enderror">
    @error('break_times.' . $index . '.end_time')
    <span class="error-message">{{ $message }}</span>
    @enderror
</div>
</td>
</tr>
@endforeach

<tr class="last-row">
<th>備考</th>
<td>
{{-- 備考 --}}
<textarea name="reason" class="@error('reason') is-invalid @enderror">{{ old('reason', $primaryData ? $primaryData->reason : '') }}</textarea>

{{-- 備考のエラーメッセージ --}}
@error('reason')
<span class="error-message">{{ $message }}</span>
@enderror

</td>
</tr>
</tbody>
</table>

</div>

<div class="button-container">
    <!-- 申請データが`null`ではない場合に、承認ステータスをチェックする -->
    @if($application)
        <!-- pendingが`true`の場合にメッセージを表示 -->
        @if($application->pending)
            <span class="message-pending">＊承認待ちのため修正はできません。</span>
        <!-- pendingが`false`の場合にメッセージを表示 -->
        @else
            <span class="message-approved">＊この日は一度承認されたので修正できません。</span>
        @endif
    @else
        <!-- 勤怠申請データが存在しない場合は修正ボタンを表示 -->
        <button type="submit" class="button update-button">修正</button>
    @endif
</div>

</form>

{{-- 元のページに戻るためのリンク --}}
<a href="{{ request()->input('redirect_to') }}" class="button back-button">戻る</a>

</div>
@endsection