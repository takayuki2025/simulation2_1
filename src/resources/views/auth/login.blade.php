@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}" />
@endsection

@section('content')
    <div class="login-page">
        <div class="login-box">
            <h2 class="title">ログイン</h2>

            <form action="login" method="POST">
                @csrf
                <label class="label-email">メールアドレス</label>
                <input type="text" class="email-form" name="email" value="{{ old('email') }}" />
                <div class="error">
                    @error('email')
                        {{ $message }}
                    @enderror
                </div>

                <label class="label-password">パスワード</label>
                <input type="password" class="password-form" name="password" />
                <div class="error">
                    @error('password')
                        {{ $message }}
                    @enderror
                </div>

                <div class="button-container">
                    <input type="submit" class="login-button" value="ログインする" />
                </div>
            </form>

            <a class="to-register" href="{{ route('register') }}">会員登録はこちら</a>
            <br />
            <a href="/admin/login">移動できないけど管理者ログインページへ</a>
        </div>
    </div>
@endsection
