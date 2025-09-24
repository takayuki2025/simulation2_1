@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/user_stamping.css') }}">
@endsection

@section('content')


<body>
    <div class="stamping-container">
        <div class="stamping-container_1">
            {{-- メール認証が完了していない場合 --}}
            @if(is_null(Auth::user()->email_verified_at))
                <h3>メール認証を完了してください。</h3>
            @else
                {{-- 勤務情報を取得し、状態を判定 --}}
                @php
                    $isClockedIn = isset($attendance) && isset($attendance->clock_in_time);
                    $isClockedOut = isset($attendance) && isset($attendance->clock_out_time);
                    $isBreaking = false;
                    for ($i = 1; $i <= 4; $i++) {
                        $breakStart = 'break_start_time_' . $i;
                        $breakEnd = 'break_end_time_' . $i;
                        if (isset($attendance) && isset($attendance->$breakStart) && empty($attendance->$breakEnd)) {
                            $isBreaking = true;
                            break;
                        }
                    }
                @endphp

{{ $greeting }}

                {{-- 勤務外（出勤前） --}}
                @if(!$isClockedIn)
                    <h3>勤務外</h3>
                    <h3>出勤ボタンを押してください。</h3>
                    <form action="{{ route('attendance.clock_in') }}" method="post">
                        @csrf
                        <input type="submit" class="submit_form" value="出勤">
                    </form>

                {{-- 退勤済み --}}
                @elseif($isClockedOut)
                    <h3>退勤済</h3>
                    <h3>本日の勤務は終了しました。</h3>

                {{-- 休憩中 --}}
                @elseif($isBreaking)
                    <h3>休憩中</h3>
                    <h3>休憩戻りボタンを押してください。</h3>
                    <form action="{{ route('attendance.break_end') }}" method="post">
                        @csrf
                        <input type="submit" class="submit_form" value="休憩戻">
                    </form>

                {{-- 勤務中（休憩中ではない） --}}
                @else
                    <h3>勤務中</h3>
                    <h3>休憩または退勤ボタンを押してください。</h3>
                    <form action="{{ route('attendance.clock_out') }}" method="post">
                        @csrf
                        <input type="submit" class="submit_form" value="退勤">
                    </form>
                    <form action="{{ route('attendance.break_start') }}" method="post">
                        @csrf
                        <input type="submit" class="submit_form" value="休憩入">
                    </form>
                @endif
            @endif
        </div>
    </div>
</body>
@endsection