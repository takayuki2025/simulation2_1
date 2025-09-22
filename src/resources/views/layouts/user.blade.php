<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}" />
    @yield('css')
</head>

<body>
    <header class="header">
        <div class="header_layout">
            <img class="company" src="/title_logo/logo.svg" alt="会社名">

<div class="a_tags">
            <a class="word1" href="/attendance">仮勤怠パス</a>
            <a class="word2" href="">勤怠一覧</a>
            <a class="word3" href="">申請</a>
        <form class="" action="{{ route('logout') }}" method="post">
            @csrf
            <button class="word4">ログアウト</button>
        </form>
</div>

        </div>
    </header>

    <main>
        @yield('content')
    </main>

</body>

</html>