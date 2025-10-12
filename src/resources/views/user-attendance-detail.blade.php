@extends('layouts.user-and-admin')

@section('css')
    @if ($application)
        <link rel="stylesheet" href="{{ asset('css/admin-apply-judgement.css') }}" />
    @else
        <link rel="stylesheet" href="{{ asset('css/user-attendance-detail.css') }}" />
    @endif
@endsection

@section('content')
    <div class="container">
        <h2 class="page-title">勤怠詳細</h2>

        <div class="attendance-detail-frame">
            {{-- 勤怠申請データが存在するかどうかで表示内容を切り替える --}}
            @if ($application)
                <div id="attendance-detail-view" class="read-only-view">
                    <table class="detail-table">
                        <tbody>
                            <tr>
                                <th>名前</th>

                                <td><span>{{ $user->name }}</span></td>
                            </tr>

                            <tr>
                                <th>日付</th>

                                <td><span>{{ \Carbon\Carbon::parse($date)->format('Y年　　　　　n月j日') }}</span></td>
                            </tr>

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
                            @if ($hasNoBreakData)
                                <tr>
                                    <th>休憩</th>
                                    <td colspan="2">
                                        <span>休憩なし</span>
                                    </td>
                                </tr>
                            @else
                                {{-- 休憩データがある場合（1つ以上ある場合）はループで表示する --}}
                                @foreach ($breaksToDisplay as $index => $break)
                                    <tr>
                                        @if ($index === 0)
                                            <th>休憩</th>
                                        @else
                                            <th>休憩{{ $index + 1 }}</th>
                                        @endif
                                        <td class="time-inputs">
                                            <span>{{ $break['start_time'] ?? '-' }}</span>
                                            <span>　　〜</span>

                                            <span>　　{{ $break['end_time'] ?? '-' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif

                            <tr class="last-row">
                                <th>備考</th>
                                <td>
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
                    @if ($attendance)
                        <input type="hidden" name="attendance_id" value="{{ $attendance->id }}" />
                    @endif

                    <input type="hidden" name="user_id" value="{{ $user->id }}" />
                    <input type="hidden" name="checkin_date" value="{{ \Carbon\Carbon::parse($date)->format('Y-m-d') }}" />

                    @error('checkin_date')
                        <p class="error-message date-error">{{ $message }}</p>
                    @enderror

                    <input type="hidden" name="redirect_to" value="{{ request()->input('redirect_to') }}" />

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
                                    <div class="input-block">
                                        <input type="text" name="clock_in_time" value="{{ old('clock_in_time', $primaryData && $primaryData->clock_in_time ? \Carbon\Carbon::parse($primaryData->clock_in_time)->format('H:i') : '') }}" class="@error('clock_in_time') is-invalid @enderror" />
                                        @error('clock_in_time')
                                            <span class="error-message">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <span>〜</span>

                                    <div class="input-block">
                                        <input type="text" name="clock_out_time" value="{{ old('clock_out_time', $primaryData && $primaryData->clock_out_time ? \Carbon\Carbon::parse($primaryData->clock_out_time)->format('H:i') : '') }}" class="@error('clock_out_time') is-invalid @enderror" />
                                        @error('clock_out_time')
                                            <span class="error-message">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </td>
                            </tr>
                            {{-- break_times配列をPOST送信するための入力フィールド --}}
                            @foreach ($formBreakTimes as $index => $breakTime)
                                <tr>
                                    @if ($index === 0)
                                        <th>休憩</th>
                                    @else
                                        <th>休憩{{ $index + 1 }}</th>
                                    @endif
                                    <td class="time-inputs">
                                        <div class="input-block">
                                            <input type="text" name="break_times[{{ $index }}][start_time]" value="{{ old('break_times.' . $index . '.start_time', $breakTime['start_time'] ?? '') }}" class="@error('break_times.' . $index . '.start_time') is-invalid @enderror" />
                                            @error('break_times.' . $index . '.start_time')
                                                <span class="error-message">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <span>〜</span>

                                        <div class="input-block">
                                            <input type="text" name="break_times[{{ $index }}][end_time]" value="{{ old('break_times.' . $index . '.end_time', $breakTime['end_time'] ?? '') }}" class="@error('break_times.' . $index . '.end_time') is-invalid @enderror" />
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
            @if ($application)
                @if ($application->pending)
                    <span class="message-pending">＊承認待ちのため修正はできません。</span>
                @else
                    <span class="message-approved">＊この日は一度承認されたので修正できません。</span>
                @endif
            @else
                <button type="submit" form="attendance-form" class="button update-button">修 正</button>
            @endif
        </div>
    </div>
@endsection
