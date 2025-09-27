@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/admin_attendance_detail.css') }}">
@endsection

@section('content')

<body>
<div class="container">

<div class="title">
<h2 class="tile_1">勤怠詳細</h2>
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
            <td>
                <!-- 勤怠データが存在しない場合でも、ユーザー名は表示されるように修正 -->
                {{ $attendance ? $attendance->user->name : $user->name }}
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
                {{-- ★修正箇所: 勤怠データ ($attendance) ではなく、優先度の高いデータソース ($primaryData) を参照する --}}
                {{-- $primaryData はコントローラーで ($application ?? $attendance) として定義されています。 --}}
                <input type="text" name="clock_in_time" 
                    value="{{ $primaryData && $primaryData->clock_in_time ? \Carbon\Carbon::parse($primaryData->clock_in_time)->format('H:i') : '' }}">
                <span>〜</span>
                <input type="text" name="clock_out_time" 
                    value="{{ $primaryData && $primaryData->clock_out_time ? \Carbon\Carbon::parse($primaryData->clock_out_time)->format('H:i') : '' }}">
            </td>
        </tr>
        {{-- break_times配列をPOST送信 --}}
        @foreach($formBreakTimes as $index => $breakTime)
        <tr>
            <th>休憩{{ $index + 1 }}</th>
            <td class="time-inputs">
                {{-- name属性を配列形式 [index][key] にすることで、連想配列としてPOSTされます --}}
                <input type="text" name="break_times[{{ $index }}][start_time]" value="{{ $breakTime['start_time'] ?? '' }}">
                <span>〜</span>
                <input type="text" name="break_times[{{ $index }}][end_time]" value="{{ $breakTime['end_time'] ?? '' }}">
            </td>
        </tr>
        @endforeach
        <tr class="last-row">
            <th>備考</th>
            <td>
                {{-- 備考欄も $primaryData を参照するように修正 --}}
                <textarea name="reason">{{ $primaryData ? $primaryData->reason : '' }}</textarea>
            </td>
        </tr>
    </tbody>
</table>

</div>

<div class="button-container">

    <button type="submit" class="button update-button">修正</button>

</div>

</form>

{{-- 元のページに戻るためのリンク --}}
<a href="{{ request()->input('redirect_to') }}" class="button back-button">戻る</a>

</div>
@endsection