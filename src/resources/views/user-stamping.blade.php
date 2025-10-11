@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/user-stamping.css') }}">
@endsection

@section('content')

<div class="container">

    <div class="stamping-container">
        <div class="stamping-container-inner">

        <!-- logとともに重要な勤務終了処理がエラーの時のメッセージです。 -->
        @if (session('error'))
            <div class="alert error-alert">
                {{ session('error') }}
            </div>
        @endif

    {{-- メール認証が完了していない場合 --}}
        @if(is_null(Auth::user()->email_verified_at))
            <h3>メール認証処理が完了しませんでした。</h3>
        @else
        @if(!$isClockedIn)
            <h4 class="status">勤務外</h4><br>
            {{-- コントローラから渡された初期値を表示 --}}
                <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }})</p>
                <p class="time-only" id="currentTime">{{ $currentTime }}</p>
            <form action="{{ route('attendance.clock_in') }}" method="post">
                @csrf
                <input type="submit" class="submit-primary" value="出勤">
            </form>
        @elseif($isClockedOut)
            <h4 class="status">退勤済</h4><br>
                <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }})</p>
                <p class="time-only" id="currentTime">{{ $currentTime }}</p>
            <p class="finish-message">お疲れ様でした。</p>
        @elseif($isBreaking)
            <h4 class="status">休憩中</h4><br>
                <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }})</p>
                <p class="time-only" id="currentTime">{{ $currentTime }}</p>
            <form action="{{ route('attendance.break_end') }}" method="post">
                @csrf
                <input type="submit" class="submit-primary" value="休憩戻">
            </form>
        @else
            <h4 class="status">出勤中</h4><br>
                <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }})</p>
                <p class="time-only" id="currentTime">{{ $currentTime }}</p>
            <div class="submit-out-or-break">
            <form action="{{ route('attendance.create') }}" method="post">
                @csrf
                <input type="submit" class="submit-primary" value="退勤">
            </form>
            <form action="{{ route('attendance.break_start') }}" method="post">
                @csrf
                <input type="submit" class="submit-primary" value="休憩入">
            </form>
            </div>
        @endif
        @endif
    </div>

</div>

@endsection