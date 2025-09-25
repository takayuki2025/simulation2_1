@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/user_stamping.css') }}">
@endsection

@section('content')


<body>

{{ $greeting }}

    <div class="stamping-container">
        <div class="stamping-container_1">
            {{-- メール認証が完了していない場合 --}}
            @if(is_null(Auth::user()->email_verified_at))
                <h3>メール認証処理が完了しませんでした。</h3>
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

                        // 現在の日時と曜日を取得
    date_default_timezone_set('Asia/Tokyo');
    $currentDate = date('Y年m月d日');
    $dayOfWeek = date('w');
    $dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];
    $currentDay = $dayOfWeekMap[$dayOfWeek];
    $currentTime = date('H:i');
                @endphp



                {{-- 勤務外（出勤前） --}}
                @if(!$isClockedIn)
                    <h4 class="status">勤務外</h4><br>

                <p class="day_and_week">{{ $currentDate }} ({{ $currentDay }}曜日)</p>
                <p class="time_only">{{ $currentTime }}</p>

                    <form action="{{ route('attendance.clock_in') }}" method="post">
                        @csrf
                        <input type="submit" class="submit_form" value="出勤">
                    </form>

                {{-- 退勤済み --}}
                @elseif($isClockedOut)
                    <h4 class="status">退勤済</h4><br>

                <p class="day_and_week">{{ $currentDate }} ({{ $currentDay }}曜日)</p>
                <p class="time_only">{{ $currentTime }}</p>

                    <h3>お疲れ様でした。</h3>

                {{-- 休憩中 --}}
                @elseif($isBreaking)
                    <h4 class="status">休憩中</h4><br>
                <p class="day_and_week">{{ $currentDate }} ({{ $currentDay }}曜日)</p>
                <p class="time_only">{{ $currentTime }}</p>

                    <form action="{{ route('attendance.break_end') }}" method="post">
                        @csrf
                        <input type="submit" class="submit_form" value="休憩戻">
                    </form>

                {{-- 勤務中（休憩中ではない） --}}
                @else
                    <h3 class="status">勤務中</h3><br>
                <p class="day_and_week">{{ $currentDate }} ({{ $currentDay }}曜日)</p>
                <p class="time_only">{{ $currentTime }}</p>
                <div class="submit_out_or_break">
                    <form action="{{ route('attendance.clock_out') }}" method="post">
                        @csrf
                        <input type="submit" class="submit_form_2" value="退勤">
                    </form>
                    <form action="{{ route('attendance.break_start') }}" method="post">
                        @csrf
                        <input type="submit" class="submit_form_2" value="休憩入">
                    </form>
                </div>
                @endif
            @endif
        </div>
    </div>
</body>
@endsection