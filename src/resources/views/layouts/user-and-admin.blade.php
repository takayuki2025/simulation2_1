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
        <div class="header-layout">
            <img class="company" src="/title_logo/logo.svg" alt="会社名">
            <div class="a-tags">
        @auth
            {{-- 認証済みユーザーの場合、メール認証が完了しているかチェック --}}
            @if (Auth::user()->hasVerifiedEmail())

                {{-- 【認証完了ユーザー向けナビゲーション】 --}}
                @admin
                    {{-- 管理者向けリンク --}}
                    <a class="model-1-button" href="{{ route('admin.attendance.list.index') }}">勤怠一覧</a>
                    <a class="model-2-button" href="{{ route('admin.staff.list.index') }}">スタッフ一覧</a>
                    <a class="apply-list-button" href="{{ route('apply.list') }}">申請一覧</a>
                @else
                    {{-- 一般ユーザー向けリンク (退勤状態により出し分け) --}}
                    @php
                        // 現在日の勤怠データに退勤時刻が設定されているかチェック
                        // $attendance変数がこのレイアウトに渡されている前提で処理を継続
                        $isClockedOut = isset($attendance) && isset($attendance->clock_out_time);
                    @endphp
                    @if ($isClockedOut)
                        {{-- 退勤済みの場合に表示するリンク --}}
                        <a class="model-1-button" href="{{ route('user.month.index') }}">今月の勤怠一覧</a>
                        <a class="model-2-button" href="{{ route('apply.list') }}">申請一覧</a>
                    @else
                        {{-- 勤務中の場合や、まだ出勤打刻をしていない場合に表示するリンク --}}
                        <a class="model-1-button" href="{{ route('user.stamping.index') }}">勤怠</a>
                        <a class="model-2-button" href="{{ route('user.month.index') }}">勤怠一覧</a>
                        <a class="apply-list-button" href="{{ route('apply.list') }}">申請</a>
                    @endif
                @endadmin

                {{-- 【ログアウトボタン】メール認証完了者のみに表示する --}}
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button class="logout-button">ログアウト</button>
                </form>

            @endif
        @endauth
            </div>
        </div>
    </header>

<main>
    @yield('content')
</main>

</body>

</html>