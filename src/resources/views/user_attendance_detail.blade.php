@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/user_attendance_detail.css') }}">
@endsection

@section('content')

<body>
<div class="container">
<div class="title">
<h2 class="tile_1">勤怠詳細</h2>
</div>

    <div class="attendance-detail-frame">
        <form action="{{ route('application.create') }}" method="POST" id="attendance-form">
            @csrf
            <!-- 勤怠データが存在する場合、IDを渡す -->
            @if($attendance)
            <input type="hidden" name="id" value="{{ $attendance->id }}">
            <input type="hidden" name="attendance_id" value="{{ $attendance->id }}">
            @endif
            <input type="hidden" name="checkin_date" value="{{ \Carbon\Carbon::parse($date)->format('Y-m-d') }}">

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
                            <input type="text" name="clock_in_time" value="{{ $attendance && $attendance->clock_in_time ? \Carbon\Carbon::parse($attendance->clock_in_time)->format('H:i') : '' }}">
                            <span>〜</span>
                            <input type="text" name="clock_out_time" value="{{ $attendance && $attendance->clock_out_time ? \Carbon\Carbon::parse($attendance->clock_out_time)->format('H:i') : '' }}">
                        </td>
                    </tr>
                    @foreach($formBreakTimes as $index => $breakTime)
                    <tr>
                        <th>休憩{{ $index + 1 }}</th>
                        <td class="time-inputs">
                            <input type="text" name="break_times[{{ $index }}][start_time]" value="{{ $breakTime['start_time'] }}">
                            <span>〜</span>
                            <input type="text" name="break_times[{{ $index }}][end_time]" value="{{ $breakTime['end_time'] }}">
                        </td>
                    </tr>
                    @endforeach
                    <tr class="last-row">
                        <th>備考</th>
                        <td>
                            <textarea name="reason">{{ $attendance ? $attendance->reason : '' }}</textarea>
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
</div>

</body>

@endsection