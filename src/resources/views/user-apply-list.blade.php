@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/user-apply-list.css') }}">
@endsection

@section('content')

<div class="container">

    <h2 class="page-title">申請履歴</h2>

    <div class="tab-container">
        <a href="?pending=true" class="tab-link {{ request('pending', 'true') == 'true' ? 'active' : '' }}">承認待ち</a>
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
        <tr class="application-row" data-pending="{{ $application['pending'] ? 'true' : 'false' }}">
            <td><span style="font-weight: bold;">{{ $application['status_text'] }}</span></td>
            <td>{{ $application['user_name'] }}</td>
            <td>{{ $application['target_date_display'] }}</td>
            <td class="user-apply-reason">{{ $application['reason'] }}</td>
            <td>{{ $application['created_at_display'] }}</td>
            <td>
{{-- 日付が有効な場合のみリンクを表示 --}}
        @if ($application['has_target_date'])
                <a href="{{ $application['detail_url'] }}" class="detail-button">詳細</a>
        @else
                <span style="color: #777; font-size: 0.8em;">日付不明</span>
        @endif
            </td>
        </tr>
    @endforeach
        @if ($applications->isEmpty())
        <tr>
            <td colspan="6" style="text-align: center; color: #777;">該当する申請はありません。</td>
        </tr>
        @endif
    </tbody>
    </table>
</div>

@endsection