@extends('layouts.user-and-admin')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
@endsection

@section('content')

<div class="register-page">
    <div class="register-box">
        <h2 class="title">会員登録</h2>

        <form action="register" method="POST">
            @csrf
            <label class="label-name">名前</label>
            <input type="text" class="name-form" name="name" value="{{ old('name') }}" />
            <div class="error">
                @error('name')
                {{ $message }}
                @enderror
            </div>

            <label class="label-email">メールアドレス</label>
            <input type="text" class="email-form" name="email" value="{{ old('email') }}" />
            <div class="error">
                @error('email')
                {{ $message }}
                @enderror
            </div>

            <label class="label-password">パスワード</label>
            <input type="password" class="password-form" name="password">
            <div class="error">
                @error('password')
                {{ $message }}
                @enderror
            </div>

            <label class="label-conf-password">パスワード確認</label>
            <input type="password" class="password-form" name="password_confirmation">
            <div class="error">
                @error('password_confirmation')
                {{ $message }}
                @enderror
            </div>

            <div class="button-container">
                <input type="submit" class="register-button" value="登録する">
            </div>
        </form>

        <a class="to-login" href="{{ route('login') }}">ログインはこちら</a>

    </div>
</div>

@endsection