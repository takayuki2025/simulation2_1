@extends('layouts.user-and-admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/user-stamping.css') }}">
@endsection

@section('content')

<body>
{{-- ★ここからcontainerで全体を囲みます --}}
<div class="container">

<h3>{{ $greeting }}</h3>

<div class="stamping-container">
    <div class="stamping-container-1">
    {{-- メール認証が完了していない場合 --}}
    @if(is_null(Auth::user()->email_verified_at))
        <h3>メール認証処理が完了しませんでした。</h3>
    @else
    {{-- 勤務状態の判定と表示の切り替え --}}

        {{-- 勤務外（出勤前） --}}
        @if(!$isClockedIn)
            <h4 class="status">勤務外</h4><br>
            {{-- コントローラから渡された初期値を表示 --}}
            <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }})</p>
            <p class="time-only" id="currentTime">{{ $currentTime }}</p>

            <form action="{{ route('attendance.clock_in') }}" method="post">
                @csrf
                <input type="submit" class="submit-form" value="出勤">
            </form>

        {{-- 退勤済み --}}
        @elseif($isClockedOut)
            <h4 class="status">退勤済</h4><br>
            <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }})</p>
            <p class="time-only" id="currentTime">{{ $currentTime }}</p>

            <h3>お疲れ様でした。</h3>

        {{-- 休憩中 --}}
        @elseif($isBreaking)
            <h4 class="status">休憩中</h4><br>
            <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }})</p>
            <p class="time-only" id="currentTime">{{ $currentTime }}</p>

            <form action="{{ route('attendance.break_end') }}" method="post">
                @csrf
                <input type="submit" class="submit-form" value="休憩戻">
            </form>

        {{-- 勤務中（休憩中ではない） --}}
        @else
            <h3 class="status">勤務中</h3><br>
            <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }})</p>
            <p class="time-only" id="currentTime">{{ $currentTime }}</p>
            <div class="submit-out-or-break">
                <form action="{{ route('attendance.create') }}" method="post">
                    @csrf
                    <input type="submit" class="submit-form-2" value="退勤">
                </form>
                <form action="{{ route('attendance.break_start') }}" method="post">
                    @csrf
                    <input type="submit" class="submit-form-2" value="休憩入">
                </form>
            </div>
        @endif
    @endif

    </div>

</div>

</div>
{{-- ★containerの閉じタグ --}}

</body>
@endsection