@extends('layouts.user-and-admin')

@section('css')

{{-- 修正: CSSファイル名をケバブケースに統一 --}}
<link rel="stylesheet" href="{{ asset('css/admin-attendance-detail.css') }}">
@endsection

@section('content')

<body>
<div class="container">

<div class="title">
{{-- 修正: tile_1 -> tile-1 --}}
<h2 class="tile-1">勤怠詳細</h2>
</div>

<div class="attendance-detail-frame">
<form action="{{ route('admin.attendance.approve') }}" method="POST" id="attendance-form">
@csrf
<!-- 勤怠データが存在する場合、IDを渡す -->
@if($attendance)
<input type="hidden" name="attendance_id" value="{{ $attendance->id }}">
<!-- スタッフのユーザーIDを渡すための隠しフィールドを追加 -->
<input type="hidden" name="user_id" value="{{ $attendance->user_id }}">
@else
<!-- 勤怠データが存在しない場合、ビューに渡されたユーザーIDを隠しフィールドとして渡す -->
<input type="hidden" name="user_id" value="{{ $user->id }}">
@endif
<input type="hidden" name="checkin_date" value="{{ \Carbon\Carbon::parse($date)->format('Y-m-d') }}">

{{-- 元のページに戻るためのURLを隠しフィールドとして追加 --}}
<input type="hidden" name="redirect_to" value="{{ request()->input('redirect_to') }}">

<table class="detail-table">
    <tbody>
        <tr>
            <th>名前</th>
            {{-- 修正: detail-user_name -> detail-user-name --}}
            <td class="detail-user-name">
                <!-- 勤怠データが存在しない場合でも、ユーザー名は表示されるように修正 -->
                {{ $attendance ? $attendance->user->name : $user->name }}
            </td>
        </tr>
        <tr>
            <th>日付</th>
            <td>
                {{ \Carbon\Carbon::parse($date)->format('　 Y年　　　　 n月j日') }}
            </td>
        </tr>
        <tr>
            <th>出勤・退勤</th>
            <td class="time-inputs">
                {{-- 1. 出勤時刻ブロック --}}
                <div class="input-block">
                    {{-- 修正: old()を最優先し、次に既存データを使用 --}}
                    <input type="text" name="clock_in_time" 
                           value="{{ old('clock_in_time', $primaryData && $primaryData->clock_in_time ? \Carbon\Carbon::parse($primaryData->clock_in_time)->format('H:i') : '') }}"
                           class="@error('clock_in_time') is-invalid @enderror">
                    {{-- 出勤時刻のエラーメッセージ --}}
                    <span class="error-message">@error('clock_in_time') {{ $message }} @enderror</span>
                </div>
                
                <span>〜</span>

                {{-- 2. 退勤時刻ブロック --}}
                <div class="input-block">
                    {{-- 修正: old()を最優先し、次に既存データを使用 --}}
                    <input type="text" name="clock_out_time" 
                           value="{{ old('clock_out_time', $primaryData && $primaryData->clock_out_time ? \Carbon\Carbon::parse($primaryData->clock_out_time)->format('H:i') : '') }}"
                           class="@error('clock_out_time') is-invalid @enderror">
                    {{-- 退勤時刻のエラーメッセージ --}}
                    <span class="error-message">@error('clock_out_time') {{ $message }} @enderror</span>
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
                    {{-- 修正: old()を最優先し、次に既存データを使用 --}}
                    {{-- name属性を配列形式 [index][key] にすることで、連想配列としてPOSTされます --}}
                    <input type="text" name="break_times[{{ $index }}][start_time]" 
                           value="{{ old('break_times.' . $index . '.start_time', $breakTime['start_time'] ?? '') }}"
                           class="@error('break_times.' . $index . '.start_time') is-invalid @enderror">
                    {{-- 休憩開始時刻のエラーメッセージ --}}
                    <span class="error-message">@error('break_times.' . $index . '.start_time') {{ $message }} @enderror</span>
                </div>
                
                <span>〜</span>

                {{-- 休憩終了時刻ブロック --}}
                <div class="input-block">
                    {{-- 修正: old()を最優先し、次に既存データを使用 --}}
                    <input type="text" name="break_times[{{ $index }}][end_time]" 
                           value="{{ old('break_times.' . $index . '.end_time', $breakTime['end_time'] ?? '') }}"
                           class="@error('break_times.' . $index . '.end_time') is-invalid @enderror">
                    {{-- 休憩終了時刻のエラーメッセージ --}}
                    <span class="error-message">@error('break_times.' . $index . '.end_time') {{ $message }} @enderror</span>
                </div>
            </td>
        </tr>
        @endforeach
        <tr class="last-row">
            <th>備考</th>
            <td>
                {{-- 修正: old()を最優先し、次に既存データを使用 --}}
                <textarea name="reason" class="@error('reason') is-invalid @enderror">{{ old('reason', $primaryData ? $primaryData->reason : '') }}</textarea>

                {{-- 備考のエラーメッセージ --}}
                <span class="error-message">@error('reason') {{ $message }} @enderror</span>
            </td>
        </tr>
    </tbody>
</table>

</div>

<div class="button-container">

    <button type="submit" class="button update-button">修 正</button>

</div>

</form>

<!-- {{-- 元のページに戻るためのリンク --}}
<a href="{{ request()->input('redirect_to') }}" class="button back-button">戻る</a> -->

</div>
@endsection