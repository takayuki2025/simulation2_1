<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;

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

        // View Composer の追加
        View::composer('layouts.user-and-admin', function ($view) {
            
            // ユーザーがログインしている場合にのみ実行
            if (Auth::check()) {
                $user = Auth::user();
                
                // ユーザーの当日の勤怠レコードを取得
                // 複数レコードがある可能性があるため、最新のレコードを取得
                $attendance = Attendance::where('user_id', $user->id)
                                    ->whereDate('checkin_date', today())
                                    ->latest() 
                                    ->first();
                
                // 退勤時刻が設定されているかチェック
                $isClockedOut = isset($attendance) && isset($attendance->clock_out_time);
                
                // レイアウトに $isClockedOut と $attendance 変数を渡す
                $view->with('attendance', $attendance)
                    ->with('isClockedOut', $isClockedOut);
            }
        });
    }
}