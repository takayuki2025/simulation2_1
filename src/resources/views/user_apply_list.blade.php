@extends('layouts.user_and_admin')

@section('css')
{{-- スタイルはadmin_apply_list.cssを流用することを想定 --}}

<link rel="stylesheet" href="{{ asset('css/user_apply_list.css') }}">
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
<tr class="application-row" data-pending="{{ $application['pending'] ? 'true' : 'false' }}">
<td>
<span style="color: {{ $application['status_color'] }}; font-weight: bold;">{{ $application['status_text'] }}</span>
</td>
<td>{{ $application['user_name'] }}</td>
<td>
{{-- コントローラで整形済みの表示用日付を使用 --}}
{{ $application['target_date_display'] }}
</td>
<td>{{ $application['reason'] }}</td>
<td>{{ $application['created_at_display'] }}</td>
<td>
{{-- 日付が有効な場合のみリンクを表示 --}}
@if ($application['has_target_date'])
<a href="{{ $application['detail_url'] }}" class="detail-link">詳細</a>
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