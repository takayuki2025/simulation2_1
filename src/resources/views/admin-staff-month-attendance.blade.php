@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin-staff-month-attendance.css') }}" />
@endsection

@section('content')
    <div class="container">
        <h2 class="page-title">{{ $staffUser->name }}さんの勤怠</h2>

        <div class="date-nav-frame">
            <div class="calendar-title">
                <div class="nav">
                    <a href="?year={{ $prevMonth->year }}&month={{ $prevMonth->month }}" class="arrow-left">
                        <span class="nav-arrow">&#x2B05;</span>
                        前月
                    </a>
                </div>
                <h2>
                    📅
                    <span id="current-date-display">{{ $date->format('Y/m') }}</span>
                </h2>
                <div class="nav">
                    <a href="?year={{ $nextMonth->year }}&month={{ $nextMonth->month }}" class="arrow-right">
                        翌月
                        <span class="nav-arrow">&#x27A1;</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="attendance-table-frame">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($monthlyAttendanceData as $dayData)
                        <tr class="{{ $dayData['isSunday'] ? 'sunday' : '' }} {{ $dayData['isSaturday'] ? 'saturday' : '' }}">
                            <td class="day-column">{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ str_pad($dayData['day'], 2, '0', STR_PAD_LEFT) }}({{ $dayData['dayOfWeek'] }})</td>
                            @if ($dayData['attendance'])
                                <td>{{ $dayData['clockInTime'] }}</td>
                                <td>{{ $dayData['clockOutTime'] }}</td>
                                <td>{{ $dayData['breakTimeDisplay'] }}</td>
                                <td>{{ $dayData['workTimeDisplay'] }}</td>
                                <td>
                                    @if (\Carbon\Carbon::parse($dayData['dateString'])->lte($today))
                                        <a href="{{ route('admin.user.attendance.detail.index', ['id' => $dayData['attendance']->user_id, 'date' => $dayData['dateString'], 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
                                    @else
                                        &nbsp;
                                    @endif
                                </td>
                            @else
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>

                                <td>
                                    @if (\Carbon\Carbon::parse($dayData['dateString'])->lte($today))
                                        <a href="{{ route('admin.user.attendance.detail.index', ['id' => $staffUser->id, 'date' => $dayData['dateString'], 'redirect_to' => request()->fullUrl()]) }}" class="detail-button">詳細</a>
                                    @else
                                        &nbsp;
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="csv-area">
            <form action="{{ route('admin.staff.attendance.export') }}" method="POST" class="csv-button">
                @csrf
                <input type="hidden" name="user_id" value="{{ $staffUser->id }}" />
                <input type="hidden" name="year" value="{{ $year }}" />
                <input type="hidden" name="month" value="{{ $month }}" />
                <button type="submit">CSV出力</button>
            </form>
        </div>
    </div>
@endsection
