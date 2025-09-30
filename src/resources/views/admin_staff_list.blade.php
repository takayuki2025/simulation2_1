@extends('layouts.user_and_admin')

@section('css')

<link rel="stylesheet" href="{{ asset('css/admin_staff_list.css') }}">
@endsection

@section('content')

<h2 class="page-title">スタッフ一覧</h2>

<div class="container">
    <table class="staff-table">
        <thead>
            <tr>
                <th>名前</th>
                <th>メールアドレス</th>
                <th>月次勤怠</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                {{-- 修正: ハードコードされたID(1)ではなく、ロールで管理者をフィルタリングする --}}
                @if ($user->role !== 'admin')
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                                    <a href="{{ route('admin.staff.month.index', ['id' => $user->id]) }}">
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