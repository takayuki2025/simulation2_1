<!DOCTYPE html>

<html lang="ja">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>勤怠管理アプリ</title>
        <link rel="stylesheet" href="{{ asset('css/common.css') }}" />
        @yield('css')
    </head>

    <body>
        <header class="header">
            <div class="header-layout">
                <img class="company" src="/title_logo/logo.svg" alt="会社名" />
                <div class="a-tags">
                    @auth
                        @if (Auth::user()->hasVerifiedEmail())
                            @admin

                            <a class="model-1-button" href="{{ route('admin.attendance.list.index') }}">勤怠一覧</a>
                            <a class="model-2-button" href="{{ route('admin.staff.list.index') }}">スタッフ一覧</a>
                            <a class="apply-list-button" href="{{ route('apply.list') }}">申請一覧</a>
                        @else
                            @if ($isClockedOut)
                                <a class="model-1-button" href="{{ route('user.month.index') }}">今月の出勤一覧</a>
                                <a class="model-2-button" href="{{ route('apply.list') }}">申請一覧</a>
                            @else
                                <a class="model-1-button" href="{{ route('user.stamping.index') }}">勤怠</a>
                                <a class="model-2-button" href="{{ route('user.month.index') }}">勤怠一覧</a>
                                <a class="apply-list-button" href="{{ route('apply.list') }}">申請</a>
                            @endif
                            @endadmin

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
