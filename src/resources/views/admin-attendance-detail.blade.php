@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin-attendance-detail.css') }}">
@endsection

@section('content')

<div class="container">

    <h2 class="page-title">勤怠詳細</h2>

    <!-- logとともに重要な修正エラーの時のメッセージです。 -->
    @if (session('error'))
        <div class="alert error-alert">
            {{ session('error') }}
        </div>
    @endif

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
                <td>　{{ $attendance ? $attendance->user->name : $user->name }}</td>
            </tr>
            <tr>
                <th>日付</th>
                <td>{{ \Carbon\Carbon::parse($date)->format('　Y年　　　　　　n月j日') }}</td>
            </tr>
            <tr>
                <th>出勤・退勤</th>
                <td class="time-inputs">
            <div class="input-block">
                    {{-- 修正: old()を最優先し、次に既存データを使用。$primaryDataはコントローラーで$application優先で設定済み --}}
            <input type="text" name="clock_in_time"
                    value="{{ old('clock_in_time', $primaryData && $primaryData->clock_in_time ? \Carbon\Carbon::parse($primaryData->clock_in_time)->format('H:i') : '') }}"
                    class="@error('clock_in_time') is-invalid @enderror"
                    {{-- 承認待ちの場合は入力フィールドを無効化 --}}
                    @if ($isPending) disabled @endif>
                    {{-- 出勤時刻のエラーメッセージ --}}
                <span class="error-message">@error('clock_in_time') {{ $message }} @enderror</span>
            </div>
                <span>〜</span>
                {{-- 2. 退勤時刻ブロック --}}
            <div class="input-block">
                    {{-- 修正: old()を最優先し、次に既存データを使用 --}}
            <input type="text" name="clock_out_time"
                    value="{{ old('clock_out_time', $primaryData && $primaryData->clock_out_time ? \Carbon\Carbon::parse($primaryData->clock_out_time)->format('H:i') : '') }}"
                    class="@error('clock_out_time') is-invalid @enderror"
                    @if ($isPending) disabled @endif>
                    {{-- 退勤時刻のエラーメッセージ --}}
                <span class="error-message">@error('clock_out_time') {{ $message }} @enderror</span>
            </div>
                </td>
            </tr>

            @foreach($formBreakTimes as $index => $breakTime)
                <tr>
                    {{-- 【修正】最初の休憩(index=0)は「休憩」、2回目以降は「休憩 2」のように表示する --}}
                    <th>休憩{{ $index === 0 ? '' : ($index + 1) }}</th>
                    <td class="time-inputs">
                {{-- 休憩開始時刻ブロック --}}
                <div class="input-block">
                    {{-- 修正: old()を最優先し、次に既存データを使用 --}}
                    {{-- name属性を配列形式 [index][key] にすることで、連想配列としてPOSTされます --}}
                    <input type="text" name="break_times[{{ $index }}][start_time]"
                        value="{{ old('break_times.' . $index . '.start_time', $breakTime['start_time'] ?? '') }}"
                        class="@error('break_times.' . $index . '.start_time') is-invalid @enderror"
                        @if ($isPending) disabled @endif>
                    {{-- 休憩開始時刻のエラーメッセージ --}}
                    <span class="error-message">@error('break_times.' . $index . '.start_time') {{ $message }} @enderror</span>
                </div>
                    <span>〜</span>
                {{-- 休憩終了時刻ブロック --}}
                <div class="input-block">
                    {{-- 修正: old()を最優先し、次に既存データを使用 --}}
                    <input type="text" name="break_times[{{ $index }}][end_time]"
                        value="{{ old('break_times.' . $index . '.end_time', $breakTime['end_time'] ?? '') }}"
                        class="@error('break_times.' . $index . '.end_time') is-invalid @enderror"
                        @if ($isPending) disabled @endif>
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
                    <textarea name="reason" class="@error('reason') is-invalid @enderror"
                    @if ($isPending) disabled @endif>{{ old('reason', $primaryData ? $primaryData->reason : '') }}</textarea>

                {{-- 備考のエラーメッセージ --}}
                    <span class="error-message">@error('reason') {{ $message }} @enderror</span>
                    </td>
                </tr>
            </tbody>
            </table>
        </div>

        <div class="button-container">
            @if ($isPending)
                {{-- 承認待ちの場合、修正不可メッセージを表示 --}}
                <p class="application-pending-message">
                    <span class="message-pending">*承認待ちのため修正はできません。</span>
                </p>
            @else
                {{-- 承認待ちではない場合、修正ボタンを表示 --}}
                <button type="submit" class="button approve-button">修 正</button>
            @endif
        </div>
    </form>
    </div>

</div>
@endsection