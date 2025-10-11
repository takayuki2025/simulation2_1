@extends('layouts.user-and-admin')

@section('content')
<style>

.verification-container {
    max-width: 950px;
    margin: 0 auto;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 85vh;
    width: 100%;
    box-sizing: border-box;
}

.verification-container_1 {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 2rem;
}

.status-message {
    color: green;
    margin-top: 1rem;
    font-weight: bold;
}

.verification-container_1 h3 {
    margin: 0;
}

.verification-button, .resend-button {
    margin-top: 1rem;
    padding: 0.75rem 1.5rem;
    font-weight: bold;
    text-align: center;
    border-radius: 0.5rem;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
}

.verification-button {
    margin-top: 40px;
    background-color: #d9d9d9;
    border: 1px solid #000000ff;
    color: #000000ff;
    text-decoration: none;
}

.resend-button {
    margin-top: 40px;
    font-size: 15px;
    background-color: transparent;
    color: #3182ce;
    text-decoration: none;
    padding: 0;
}

</style>

<div class="verification-container">
    <div class="verification-container_1">
        <h3>登録していただいたメールアドレスに認証メールを送付しました。</h3>
        <h3>メール認証を完了してください。</h3>

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

@endsection