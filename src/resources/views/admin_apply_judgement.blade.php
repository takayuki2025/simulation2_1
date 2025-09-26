@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/admin_apply_judgement.css') }}">
@endsection

@section('content')

<body>
<div class="container">
<div class="title">
<h2 class="tile_1">勤怠詳細</h2>
</div>

<div class="attendance-detail-frame">
    <div id="attendance-form">
        <table class="detail-table">
            <tbody>
                <tr>
                    <th>名前</th>
                    <td>
                        <span>{{ $data['name'] }}</span>
                    </td>
                </tr>
                <tr>
                    <th>日付</th>
                    <td>
                        <span>{{ $data['date'] }}</span>
                    </td>
                </tr>
                <tr>
                    <th>出勤・退勤時間</th>
                    <td class="time-inputs">
                        <span>{{ $data['clock_in_time'] }}</span>
                        <span>〜</span>
                        <span>{{ $data['clock_out_time'] }}</span>
                    </td>
                </tr>
                @foreach($data['break_times'] as $break)
                <tr>
                    <th>休憩</th>
                    <td class="time-inputs">
                        <span>{{ $break['start_time'] ?? '-' }}</span>
                        <span>〜</span>
                        <span>{{ $break['end_time'] ?? '-' }}</span>
                    </td>
                </tr>
                @endforeach
                <tr class="last-row">
                    <th>備考</th>
                    <td>
                        <span>{{ $data['reason'] }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="button-container">
    @if($data['pending'])
    <form action="{{ route('admin.apply.attendance.approve') }}" method="post">
        @csrf
        <input type="hidden" name="id" value="{{ $data['application_id'] }}">
        <button type="submit" class="button update-button">承認</button>
    </form>
    @else
        <button type="button" class="button no_update-button" disabled>承認済み</button>
    @endif
</div>

</div>

</body>

@endsection