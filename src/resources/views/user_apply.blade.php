@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/user_apply.css') }}">
@endsection

@section('content')



<body>
    @php
        // URLパラメータから年と月を取得、なければ現在の日付を使用
        $year = request()->get('year', date('Y'));
        $month = request()->get('month', date('m'));
        $date = \Carbon\Carbon::create($year, $month, 1);

        // 前月と次月のURLを生成
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();
    @endphp

    <div class="container">
        <div class="title">
            <h2 class="tile_1">申請一覧</h2>
        </div>


@endsection