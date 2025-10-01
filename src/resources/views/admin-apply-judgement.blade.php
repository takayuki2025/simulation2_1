@extends('layouts.user-and-admin')

@section('css')

{{-- 修正: CSSファイル名をケバブケースに統一 --}}
<link rel="stylesheet" href="{{ asset('css/admin-apply-judgement.css') }}">
@endsection

@section('content')

<body>
<div class="container">

{{-- 修正: tile_1 -> tile-1 --}}
<h2 class="page-title">勤怠詳細</h2>


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
                    <th>出勤・退勤</th>
                    <td class="time-inputs">
                        <span>{{ $data['clock_in_time'] }}</span>
                        <span>　〜</span>
                        <span>{{ $data['clock_out_time'] }}</span>
                    </td>
                </tr>
                {{-- $data['break_times']がJSONデコードされた配列であることを前提にループ表示 --}}
                @forelse($data['break_times'] as $index => $break)
                <tr>
                    {{-- 休憩回数を表示するために $index + 1 を使用 --}}
                    <th>休憩 {{ $index + 1 }}</th>
                    <td class="time-inputs">
                        {{-- null合体演算子 (??) で未定義の場合に備える --}}
                        <span>{{ $break['start_time'] ?? '-' }}</span>
                        <span>　〜</span>
                        <span>{{ $break['end_time'] ?? '-' }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <th>休憩</th>
                    <td class="time-inputs">
                        <span>-</span>
                        <span>〜</span>
                        <span>-</span>
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
    @if($data['pending'])
    <form action="{{ route('admin.apply.attendance.approve') }}" method="post">
        @csrf
        <input type="hidden" name="id" value="{{ $data['application_id'] }}">
        <button type="submit" class="button update-button">承 認</button>
    </form>
    @else
        {{-- 修正: no_update-button -> no-update-button --}}
        <button type="button" class="button no-update-button" disabled>承認済み</button>
    @endif
</div>

</div>

</body>

@endsection