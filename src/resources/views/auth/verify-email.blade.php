@extends('layouts.user')

@section('content')
<style>
    /* bodyとコンテナに全画面の高さを指定 */
    body, html, #app {
        height: 100%;
        margin: 0;
        padding: 0;
        margin: 0 auto;
        max-width: 1400px;
    }

    /* Flexboxを使ってコンテナを中央に配置 */
    .verification-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        width: 100%;
        box-sizing: border-box; /* パディングやボーダーを高さに含める */
    }

    /* 中央寄せされた子要素のスタイル */
    .verification-container_1 {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 2rem;
    }

    /* 成功メッセージのスタイル */
    .status-message {
        color: green;
        margin-top: 1rem;
        font-weight: bold;
    }

    /* h3タグの余白を調整して隙間をなくす */
    .verification-container_1 h3 {
        margin: 0;
    }

    /* ボタンの共通スタイル */
    .verification-button, .resend-button, .logout-link {
        margin-top: 1rem;
        padding: 0.75rem 1.5rem;
        font-weight: bold;
        text-align: center;
        border-radius: 0.5rem;
        border: none;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    /* 個別のボタンカラー */
    .verification-button {
        background-color: #d9d9d9;
        border: 1px solid #000000ff;
        color: #000000ff;
        text-decoration: none;
    }

    .resend-button {
        /* ボタンのスタイルをリセットしてテキストリンク風に */
        background-color: transparent;
        color: #3182ce;
        text-decoration: none;
        padding: 0;
    }

    .logout-link {
        background-color: #dfd0a3ff;
        color: white;
        opacity: 0.5;
    }

    /* ログアウトボタンのスタイル */
    .logout-form {
        width: 100%;
    }
</style>
<body>
    <div class="verification-container">
        <div class="verification-container_1">
            <h3>登録していただいたメールアドレスに認証メールを送付しました。</h3>
            <h3>メール認証を完了してください</h3>

            @if (session('status') === 'verification-link-sent')
                <div class="status-message">
                    新しい認証リンクが、あなたのメールアドレスに送信されました。
                </div>
            @endif

            <a href="http://localhost:8025" target="_blank" class="verification-button">認証はこちらから</a>

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="resend-button">認証メールを再送する</button>
            </form>

        </div>
    </div>
</body>
@endsection