@extends('layouts.user_and_admin')

@section('css')
{{-- スタイルはadmin_apply_list.cssを流用することを想定 --}}
<link rel="stylesheet" href="{{ asset('css/admin_apply_list.css') }}">
@endsection

@section('content')

{{-- ページタイトルとタブを中央揃えするためのラッパー --}}
<div class="content-area">
    <h2 class="page-title">申請履歴</h2>
    <div class="tab-container">
        <!-- 承認待ち: pending=true -->
        <a href="?pending=true" class="tab-link {{ request('pending', 'true') == 'true' ? 'active' : '' }}">承認待ち</a>
        <!-- 承認済み: pending=false -->
        <a href="?pending=false" class="tab-link {{ request('pending') == 'false' ? 'active' : '' }}">承認済み</a>
    </div>
</div>
{{-- /content-area --}}

{{-- テーブルのコンテナ --}}
<div class="container">
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
                        <span style="color: orange; font-weight: bold;">承認待ち</span>
                    @else
                        <span style="color: green; font-weight: bold;">承認済み</span>
                    @endif
                </td>
                <td>{{ $application->user->name }}</td>
                <td>
                    @php
                        // 申請が対象とする日付を $application->clock_out_time から抽出
                        $targetDate = null;
                        if ($application->clock_out_time) {
                            $targetDate = \Carbon\Carbon::parse($application->clock_out_time)->format('Y-m-d');
                            echo \Carbon\Carbon::parse($application->clock_out_time)->format('Y/m/d');
                        } else {
                            echo '-';
                        }
                    @endphp
                </td>
                <td>{{ $application->reason }}</td>
                <td>{{ $application->created_at->format('Y/m/d H:i') }}</td>
                <td>
                    {{-- エラーの原因となっていた $attendance 変数ではなく、対象日付 ($targetDate) をクエリパラメータとして渡す --}}
                    @if ($targetDate)
                        <a href="{{ route('user.attendance.detail.index', ['date' => $targetDate]) }}" class="detail-link">詳細へ</a>
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