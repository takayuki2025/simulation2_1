@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin-staff-list.css') }}" />
@endsection

@section('content')
    <div class="container">
        <h2 class="page-title">スタッフ一覧</h2>

        <table class="staff-table">
            <thead>
                <tr>
                    <th>名前</th>
                    <th>メールアドレス</th>
                    <th>月次勤怠</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    {{-- 修正: ハードコードされたID(1)ではなく、ロールで管理者をフィルタリングする --}}
                    @if ($user->role !== 'admin')
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                {{-- 修正: aタグとclassの間にスペースがないか確認 --}}
                                <a href="{{ route('admin.staff.month.index', ['id' => $user->id]) }}" class="detail-button">詳細</a>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
