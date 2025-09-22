<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Providers\RouteServiceProvider;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                // 管理者ユーザーは認証をスキップしてリダイレクト
                if ($user->role === 'admin') {
                    return redirect('/admin/attendance/list');
                }

                // メール認証が未完了であれば、認証通知ページにリダイレクト
                if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail()) {
                    return redirect()->route('verification.notice');
                }

                // それ以外の認証済みユーザーは指定されたホームにリダイレクト
                return redirect('/attendance');
            }
        }

        return $next($request);
    }
}