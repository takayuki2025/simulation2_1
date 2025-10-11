@extends('layouts.user-and-admin')

@section('css')
    {{-- 【スタイル切り替え】勤怠申請データ($application)の有無によって、使用するCSSファイルを切り替えます --}}
    @if($application)
        {{-- 申請データがある場合: 読み取り専用のスタイル (admin-apply-judgement.css) を適用 --}}
        <link rel="stylesheet" href="{{ asset('css/admin-apply-judgement.css') }}">
    @else
        {{-- 申請データがない場合: 修正可能なフォームのスタイル (user-attendance-detail.css) を適用 --}}
        <link rel="stylesheet" href="{{ asset('css/user-attendance-detail.css') }}">
    @endif
@endsection

@section('content')

<div class="container">

    <h2 class="page-title">勤怠詳細</h2>

    <div class="attendance-detail-frame">

    {{-- 【メインロジック】勤怠申請データが存在するかどうかで表示内容を切り替える --}}
    @if($application)
        {{-- 申請データが存在する場合（承認待ち or 承認済み）：読み取り専用表示ブロック --}}

        <div id="attendance-detail-view" class="read-only-view">
        <table class="detail-table">
        <tbody>
            {{-- 名前行 --}}
            <tr>
                <th>名前</th>
                {{-- ユーザー名を表示 --}}
                <td><span>{{ $user->name }}</span></td>
            </tr>
            {{-- 日付行 --}}
            <tr>
                <th>日付</th>
                {{-- 日付を「Y年 m月d日」形式で表示 --}}
                <td><span>{{ \Carbon\Carbon::parse($date)->format('Y年　　　　　n月j日') }}</span></td>
            </tr>
            {{-- 出勤・退勤行 --}}
            <tr>
                <th>出勤・退勤</th>
                <td class="time-inputs">
                    {{-- $primaryDataから出勤時刻をフォーマットして表示（未打刻対応） --}}
                    <span>{{ $primaryData && $primaryData->clock_in_time ? \Carbon\Carbon::parse($primaryData->clock_in_time)->format('H:i') : '未打刻' }}</span>
                    <span>　　〜</span>
                    {{-- $primaryDataから退勤時刻をフォーマットして表示（未打刻対応） --}}
                    <span>　　{{ $primaryData && $primaryData->clock_out_time ? \Carbon\Carbon::parse($primaryData->clock_out_time)->format('H:i') : '未打刻' }}</span>
                </td>
            </tr>

            {{-- $formBreakTimes（調整後の休憩データ）をループ表示 --}}
            @php
                // $applicationが存在し、$formBreakTimesに要素がある場合、最後の要素（フォーム用の空行）を除外する
                $breaksToDisplay = ($application && count($formBreakTimes) > 0) ? array_slice($formBreakTimes, 0, count($formBreakTimes) - 1) : $formBreakTimes;

                // 【追加ロジック】読み取り専用ビューで、休憩データが空の場合（何も表示されない場合）、最低限1つの空行を表示する
                // このロジックは、休憩データが全く存在しない場合にのみ実行される
                $hasNoBreakData = ($application && count($breaksToDisplay) === 0);
                if ($hasNoBreakData) {
                    $breaksToDisplay = [['start_time' => null, 'end_time' => null]];
                }
            @endphp

            {{-- 休憩データの表示ロジック --}}
            @if ($hasNoBreakData)
            {{-- 【新規追加】休憩データが全くない場合、「休憩」行に「休憩なし」と表示する --}}
            <tr>
                <th>休憩</th>
                <td colspan="2">
                    <span>休憩なし</span>
                </td>
            </tr>
            @else
            {{-- 休憩データがある場合（1つ以上ある場合）はループで表示する --}}
            @foreach($breaksToDisplay as $index => $break)
            <tr>
                {{-- 最初の休憩（$indexが0）は「休憩」として表示し、2回目以降は「休憩 2」のように連番を振る --}}
                @if ($index === 0)
                    <th>休憩</th>
                @else
                    <th>休憩 {{ $index + 1 }}</th>
                @endif
                <td class="time-inputs">
                    {{-- 休憩開始時刻を表示（データがない場合は '-'） --}}
                    <span>{{ $break['start_time'] ?? '-' }}</span>
                    <span>　　〜</span>
                    {{-- 休憩終了時刻を表示（データがない場合は '-'） --}}
                    <span>　　{{ $break['end_time'] ?? '-' }}</span>
                </td>
            </tr>
            @endforeach
            @endif

            {{-- 備考行 --}}
            <tr class="last-row">
                <th>備考</th>
                <td>
                    {{-- $primaryDataから備考を表示（データがない場合は '特になし'） --}}
                    <span>{{ $primaryData ? $primaryData->reason : '特になし' }}</span>
                </td>
            </tr>
        </tbody>
        </table>
        </div>

    @else
        {{-- 申請データが存在しない場合：修正可能なフォーム表示ブロック --}}

        <form action="{{ route('application.create') }}" method="POST" id="attendance-form">
            @csrf

            {{-- フォーム送信に必要な隠しフィールド --}}
            @if($attendance)
                <input type="hidden" name="attendance_id" value="{{ $attendance->id }}">
            @endif
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <input type="hidden" name="checkin_date" value="{{ \Carbon\Carbon::parse($date)->format('Y-m-d') }}">

            {{-- checkin_date自体のエラー表示 --}}
            @error('checkin_date')
                <p class="error-message date-error">{{ $message }}</p>
            @enderror
            <input type="hidden" name="redirect_to" value="{{ request()->input('redirect_to') }}">

            <table class="detail-table">
            <tbody>
                <tr>
                    <th>名前</th>
                    <td>　{{ $user->name }}</td>
                </tr>
                <tr>
                    <th>日付</th>
                    <td>{{ \Carbon\Carbon::parse($date)->format('　Y年　　　　　　 n月j日') }}</td>
                </tr>
                <tr>
                    <th>出勤・退勤</th>
                    <td class="time-inputs">
                        {{-- 1. 出勤時刻入力ブロック --}}
                        <div class="input-block">
                            <input type="text" name="clock_in_time"
                                {{-- old()を優先し、データがあればフォーマットして表示 --}}
                                value="{{ old('clock_in_time', $primaryData && $primaryData->clock_in_time ? \Carbon\Carbon::parse($primaryData->clock_in_time)->format('H:i') : '') }}"
                                class="@error('clock_in_time') is-invalid @enderror">
                            @error('clock_in_time')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                        <span>〜</span>
                        {{-- 2. 退勤時刻入力ブロック --}}
                        <div class="input-block">
                            <input type="text" name="clock_out_time"
                                {{-- old()を優先し、データがあればフォーマットして表示 --}}
                                value="{{ old('clock_out_time', $primaryData && $primaryData->clock_out_time ? \Carbon\Carbon::parse($primaryData->clock_out_time)->format('H:i') : '') }}"
                                class="@error('clock_out_time') is-invalid @enderror">
                            @error('clock_out_time')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                    </td>
                </tr>
                {{-- break_times配列をPOST送信するための入力フィールド --}}
                @foreach($formBreakTimes as $index => $breakTime)
                <tr>
                    {{-- 最初の休憩（$indexが0）は「休憩」として表示し、2回目以降は「休憩 2」のように連番を振る --}}
                    @if ($index === 0)
                        <th>休憩</th>
                    @else
                        <th>休憩{{ $index + 1 }}</th>
                    @endif
                    <td class="time-inputs">
                        {{-- 休憩開始時刻入力ブロック --}}
                        <div class="input-block">
                            <input type="text" name="break_times[{{ $index }}][start_time]"
                                value="{{ old('break_times.' . $index . '.start_time', $breakTime['start_time'] ?? '') }}"
                                class="@error('break_times.' . $index . '.start_time') is-invalid @enderror">
                            @error('break_times.' . $index . '.start_time')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                        <span>〜</span>
                        {{-- 休憩終了時刻入力ブロック --}}
                        <div class="input-block">
                            <input type="text" name="break_times[{{ $index }}][end_time]"
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
                        <textarea name="reason" class="@error('reason') is-invalid @enderror">{{ old('reason', $primaryData ? $primaryData->reason : '') }}</textarea>
                    @error('reason')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                    </td>
                </tr>
            </tbody>
            </table>

        </form>
    @endif
    </div>

    <div class="button-container">
    @if($application)
        {{-- 申請データが存在する場合（読み取り専用時）のステータス表示 --}}
        @if($application->pending)
            {{-- 承認待ちの場合: 修正不可メッセージ --}}
            <span class="message-pending">＊承認待ちのため修正はできません。</span>
        @else
            {{-- 承認済みの場合: 修正不可メッセージ --}}
            <span class="message-approved">＊この日は一度承認されたので修正できません。</span>
        @endif
    @else
        {{-- 勤怠申請データが存在しない場合は修正ボタンを表示 --}}
        <button type="submit" form="attendance-form" class="button update-button">修 正</button>
    @endif
    </div>

</div>

@endsection