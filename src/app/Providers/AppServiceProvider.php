<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
                // カスタムBladeディレクティブを登録
        Blade::if('admin', function () {
            // 認証済みユーザーが存在し、かつロールが 'admin' であるかを確認
            return auth()->check() && auth()->user()->role === 'admin';
        });
    }
}
