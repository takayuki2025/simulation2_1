@extends('layouts.user_and_admin')

@section('css')
<link rel="stylesheet" href="{{ asset('css/login.css') }}">
@endsection

@section('content')


    <div class="login_page">
        <div class="login_box">
        <h2 class="title">管理者ログイン</h2>

        <form action="{{ route('admin.login.post') }}" method="POST">
        @csrf
            <label class="label_form_1">メールアドレス</label>
            <input type="text" class="email_form" name="email" value="{{ old('email') }}" />
        <div class="error">
            @error('email')
            {{ $message }}
            @enderror
        </div>
            <label class="label_form_2">パスワード</label>
            <input type="password" class="password_form" name="password">
        <div class="error">
            @error('password')
            {{ $message }}
            @enderror
        </div>
        <div class="submit">
            <input type="submit" class="submit_form" value="ログインする">
        </div>
        </form>


        <a href="/login">移動できないけどユーザーログインページへ</a>
        </div>
    </div>


@endsection