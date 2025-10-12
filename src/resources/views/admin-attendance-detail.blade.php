@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin-attendance-detail.css') }}" />
@endsection

@section('content')
    <div class="container">
        <h2 class="page-title">勤怠詳細</h2>

        @if (session('error'))
            <div class="alert error-alert">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('admin.attendance.approve') }}" method="POST" id="attendance-form">
            @csrf

            @if ($attendance)
                <input type="hidden" name="attendance_id" value="{{ $attendance->id }}" />
                <input type="hidden" name="user_id" value="{{ $attendance->user_id }}" />
            @else
                <input type="hidden" name="user_id" value="{{ $user->id }}" />
            @endif
            <input type="hidden" name="checkin_date" value="{{ \Carbon\Carbon::parse($date)->format('Y-m-d') }}" />

            {{-- 元のページに戻るためのURLを隠しフィールドとして追加 --}}
            <input type="hidden" name="redirect_to" value="{{ request()->input('redirect_to') }}" />

            <div class="attendance-detail-frame">
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
                                    <input type="text" name="clock_in_time" value="{{ old('clock_in_time', $primaryData && $primaryData->clock_in_time ? \Carbon\Carbon::parse($primaryData->clock_in_time)->format('H:i') : '') }}" class="@error('clock_in_time') is-invalid @enderror" {{-- 承認待ちの場合は入力フィールドを無効化 --}} @if ($isPending) disabled @endif />
                                    <span class="error-message">
                                        @error('clock_in_time')
                                            {{ $message }}
                                        @enderror
                                    </span>
                                </div>
                                <span>〜</span>

                                <div class="input-block">
                                    <input type="text" name="clock_out_time" value="{{ old('clock_out_time', $primaryData && $primaryData->clock_out_time ? \Carbon\Carbon::parse($primaryData->clock_out_time)->format('H:i') : '') }}" class="@error('clock_out_time') is-invalid @enderror" @if ($isPending) disabled @endif />
                                    <span class="error-message">
                                        @error('clock_out_time')
                                            {{ $message }}
                                        @enderror
                                    </span>
                                </div>
                            </td>
                        </tr>

                        @foreach ($formBreakTimes as $index => $breakTime)
                            <tr>
                                <th>休憩{{ $index === 0 ? '' : $index + 1 }}</th>
                                <td class="time-inputs">
                                    <div class="input-block">
                                        <input type="text" name="break_times[{{ $index }}][start_time]" value="{{ old('break_times.' . $index . '.start_time', $breakTime['start_time'] ?? '') }}" class="@error('break_times.' . $index . '.start_time') is-invalid @enderror" @if ($isPending) disabled @endif />
                                        <span class="error-message">
                                            @error('break_times.' . $index . '.start_time')
                                                {{ $message }}
                                            @enderror
                                        </span>
                                    </div>
                                    <span>〜</span>

                                    <div class="input-block">
                                        <input type="text" name="break_times[{{ $index }}][end_time]" value="{{ old('break_times.' . $index . '.end_time', $breakTime['end_time'] ?? '') }}" class="@error('break_times.' . $index . '.end_time') is-invalid @enderror" @if ($isPending) disabled @endif />
                                        <span class="error-message">
                                            @error('break_times.' . $index . '.end_time')
                                                {{ $message }}
                                            @enderror
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        <tr class="last-row">
                            <th>備考</th>
                            <td>
                                <textarea name="reason" class="@error('reason') is-invalid @enderror" @if ($isPending) disabled @endif>{{ old('reason', $primaryData ? $primaryData->reason : '') }}</textarea>
                                <span class="error-message">
                                    @error('reason')
                                        {{ $message }}
                                    @enderror
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="button-container">
                @if ($isPending)
                    <p class="application-pending-message">
                        <span class="message-pending">*承認待ちのため修正はできません。</span>
                    </p>
                @else
                    <button type="submit" class="button approve-button">修 正</button>
                @endif
            </div>
        </form>
    </div>
@endsection
