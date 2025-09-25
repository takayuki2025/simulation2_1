@extends('layouts.user')

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
                @if ($user->id != 1)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <a href="#" class="detail-button">詳細</a>
                    </td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>

@endsection