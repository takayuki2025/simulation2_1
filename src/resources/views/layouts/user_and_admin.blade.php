<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠管理アプリ</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}" />
    @yield('css')
</head>

<body>
    <header class="header">
        <div class="header_layout">
            <img class="company" src="/title_logo/logo.svg" alt="会社名">
            <div class="a_tags">
                @auth
                    @admin
                        <a class="word1" href="{{ route('admin.attendance.list.index') }}">勤怠一覧</a>
                        <a class="word2" href="{{ route('admin.staff.list.index') }}">スタッフ一覧</a>
                        <a class="word3" href="{{ route('apply.list') }}">申請一覧</a>
                    @else
                        @php
                            // 現在日の勤怠データに退勤時刻が設定されているかチェック
                            $isClockedOut = isset($attendance) && isset($attendance->clock_out_time);
                        @endphp
                        @if ($isClockedOut)
                            {{-- 退勤済みの場合に表示するリンク --}}
                            <a class="word1" href="{{ route('user.month.index') }}">今月の勤怠一覧</a>
                            <a class="word2" href="{{ route('apply.list') }}">申請一覧</a>
                        @else
                            {{-- 勤務中の場合や、まだ出勤打刻をしていない場合に表示するリンク --}}
                            <a class="word1" href="{{ route('user.stamping.index') }}">勤怠</a>
                            <a class="word2" href="{{ route('user.month.index') }}">勤怠一覧</a>
                            <a class="word3" href="{{ route('apply.list') }}">申請</a>
                        @endif
                    @endadmin
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button class="word4">ログアウト</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

</body>

</html>