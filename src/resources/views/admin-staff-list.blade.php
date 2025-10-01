@extends('layouts.user-and-admin')

@section('css')

{{-- 修正: CSSファイル名をケバブケースに統一 --}}
<link rel="stylesheet" href="{{ asset('css/admin-staff-list.css') }}">
@endsection

@section('content')

<div class="container">


{{-- 修正: tile_1 -> tile-1 --}}
<h2 class="page-title">スタッフ一覧</h2>


    <table class="staff-table">
        <thead>
            <tr>
                <th>名 前</th>
                <th>メ ー ル ア ド レ ス</th>
                <th>月 次 勤 怠</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                {{-- 修正: ハードコードされたID(1)ではなく、ロールで管理者をフィルタリングする --}}
                @if ($user->role !== 'admin')
                <tr>
                    <td class="list-user-name">{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                                    <a href="{{ route('admin.staff.month.index', ['id' => $user->id]) }}" class="detail-button">
                詳細
            </a>
                    </td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>

@endsection