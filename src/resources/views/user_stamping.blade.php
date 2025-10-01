@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/user-stamping.css') }}">
@endsection

@section('content')

<body>

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
        {{-- コントローラから渡された初期値を表示 (JSでリアルタイム更新される) --}}
        <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }}曜日)</p>
        <p class="time-only" id="currentTime">{{ $currentTime }}</p>

        <form action="{{ route('attendance.clock_in') }}" method="post">
            @csrf
            <input type="submit" class="submit-form" value="出勤">
        </form>

    {{-- 退勤済み --}}
    @elseif($isClockedOut)
        <h4 class="status">退勤済</h4><br>
        <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }}曜日)</p>
        <p class="time-only" id="currentTime">{{ $currentTime }}</p>

        <h3>お疲れ様でした。</h3>

    {{-- 休憩中 --}}
    @elseif($isBreaking)
        <h4 class="status">休憩中</h4><br>
        <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }}曜日)</p>
        <p class="time-only" id="currentTime">{{ $currentTime }}</p>

        <form action="{{ route('attendance.break_end') }}" method="post">
            @csrf
            <input type="submit" class="submit-form" value="休憩戻">
        </form>

    {{-- 勤務中（休憩中ではない） --}}
    @else
        <h3 class="status">勤務中</h3><br>
        <p class="day-and-week" id="currentDayOfWeek">{{ $currentDate }} ({{ $currentDay }}曜日)</p>
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

</body>
@endsection
<!-- 時間と日時をリアルタイムで更新するようにしました。 -->
<script>
// ページ読み込みが完了したときに実行
document.addEventListener('DOMContentLoaded', function() {
// 表示要素を取得
const timeElement = document.getElementById('currentTime');
const dateElement = document.getElementById('currentDayOfWeek');

// 時刻と日付を更新する関数を定義
const updateDisplay = function() {
const now = new Date();

// 時刻を更新
// 💡 修正箇所: エスケープされていた '0' を正しいシングルクォートに戻しました。
const hours = String(now.getHours()).padStart(2, '0');
const minutes = String(now.getMinutes()).padStart(2, '0');
// 💡 修正箇所: テンプレートリテラル内の記述も確認し、正しく動作するようにしています。
timeElement.textContent = `${hours}:${minutes}`;

// 日付と曜日を更新
const year = now.getFullYear();
const month = now.getMonth() + 1; // getMonth()は0から始まるため
const day = now.getDate();
const dayOfWeek = now.getDay();
const dayOfWeekMap = ['日', '月', '火', '水', '木', '金', '土'];
// 💡 修正箇所: テンプレートリテラル内の記述を確認しました。
dateElement.textContent = `${year}年${month}月${day}日 (${dayOfWeekMap[dayOfWeek]}曜日)`;

};

// 初期化時にも一度実行して、表示を即座に更新する
updateDisplay();

// 1秒ごとに表示を更新
setInterval(updateDisplay, 1000);

});
</script>