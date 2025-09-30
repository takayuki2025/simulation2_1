@extends('layouts.user_and_admin')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_apply_list.css') }}">
@endsection

@section('content')



<div class="container">

    <div class="title">
<h2 class="tile_1">申請一覧</h2>
</div>

    <div class="tab-container">
        <!-- 承認待ち: pending=true -->
        <a href="?pending=true" class="tab-link {{ request('pending', 'true') == 'true' ? 'active' : '' }}">承認待ち</a>
        <!-- 承認済み: pending=false -->
        <a href="?pending=false" class="tab-link {{ request('pending') == 'false' ? 'active' : '' }}">承認済み</a>
    </div>


    <table class="apply-table">
        <thead>
            <tr>
                <th>状態</th>
                <th>名前</th>
                <th>対象日時</th>
                <th>申請理由</th>
                <th>申請日時</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($applications as $application)
            <tr class="application-row" data-pending="{{ $application->pending ? 'true' : 'false' }}">
                <td>
                    @if ($application->pending)
                        承認待ち
                    @else
                        承認済み
                    @endif
                </td>
                <td>{{ $application->user->name }}</td>
                <td>
                    @if ($application->clock_out_time)
                        {{ \Carbon\Carbon::parse($application->clock_out_time)->format('Y/m/d') }}
                    @else
                        -
                    @endif
                </td>
                <td class="user_apply_reason">{{ $application->reason }}</td>
                <td>{{ $application->created_at->format('Y/m/d') }}</td>
                <td>
                    <a href="{{ route('admin.apply.judgement.index', ['attendance_correct_request_id' => $application->id]) }}" class="detail-button">詳細</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection