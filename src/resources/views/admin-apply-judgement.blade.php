@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin-apply-judgement.css') }}" />
@endsection

@section('content')
    <div class="container">
        <h2 class="page-title">勤怠詳細</h2>

        <div class="attendance-detail-frame">
            <div id="attendance-form">
                <table class="detail-table">
                    <tbody>
                        <tr>
                            <th>名前</th>
                            <td><span>{{ $data['name'] }}</span></td>
                        </tr>
                        <tr>
                            <th>日付</th>
                            <td><span>{{ $data['date'] }}</span></td>
                        </tr>
                        <tr>
                            <th>出勤・退勤</th>
                            <td class="time-inputs">
                                <span>{{ $data['clock_in_time'] }}</span>
                                <span>　　〜</span>
                                <span>　　{{ $data['clock_out_time'] }}</span>
                            </td>
                        </tr>
                        {{-- $data['break_times']がJSONデコードされた配列であることを前提にループ表示 --}}
                        @forelse ($data['break_times'] as $index => $break)
                            <tr>
                                <th>休憩{{ $index + 1 }}</th>
                                <td class="time-inputs">
                                    <span>{{ $break['start_time'] ?? '-' }}</span>
                                    <span>　　〜</span>
                                    <span>　　{{ $break['end_time'] ?? '-' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <th>休憩</th>
                                <td>
                                    <span>休憩なし</span>
                                </td>
                            </tr>
                        @endforelse
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
            @if ($data['pending'])
                <form action="{{ route('admin.apply.attendance.approve') }}" method="post">
                    @csrf
                    <input type="hidden" name="id" value="{{ $data['application_id'] }}" />
                    <button type="submit" class="button approve-button">承 認</button>
                </form>
            @else
                <button type="button" class="button no-approve-button" disabled>承 認 済 み</button>
            @endif
        </div>
    </div>
@endsection
