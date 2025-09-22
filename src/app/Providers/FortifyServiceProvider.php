<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use App\Providers\RouteServiceProvider;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Authenticated;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
            // Fortifyのビューパスを明示的に登録
    View::addNamespace('fortify', base_path('resources/views/vendor/fortify'));

        // 新規ユーザー作成アクションを定義
        Fortify::createUsersUsing(CreateNewUser::class);

        // ログイン試行のレート制限を定義
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;

            return Limit::perMinute(30)->by($email . $request->ip());
        });

        // ログイン後のリダイレクトを制御する
        Event::listen(function (Authenticated $event) {
            $user = Auth::user();

            // 管理者ユーザーは /admin/attendance/list へリダイレクト
            if ($user->role === 'admin') {
                return redirect()->intended('/admin/attendance/list');
            }

            // それ以外のユーザーは /attendance へリダイレクト
            return redirect()->intended('/attendance');
        });
    }
}