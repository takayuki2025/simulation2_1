<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\LoginRequest;
// use Illuminate\Auth\Events\Login;
// use Illuminate\Auth\Events\Registered;


class LoginController extends Controller
{
    /**
     * ログインフォームを表示する
     */
    public function showLoginForm(Request $request)
    {
        // ログインフォームが管理者用か一般ユーザー用か、ルート名で判断する
        if ($request->routeIs('admin.login')) {
            return view('auth.admin-login');
        }

        return view('auth.login');
    }



    /**
     * ログイン処理を実行する
     * このメソッドは、通常ユーザーと管理者、両方のログイン処理を共通で扱っています。
     */
    public function login(LoginRequest $request)
    {
        // フォームリクエストによってバリデーションは完了しています
        $credentials = $request->only('email', 'password');

        // 管理者ログインページからのリクエストか確認
        $isAdminLogin = $request->isAdminLogin(); // LoginRequestで定義したメソッドを使用

        // 入力されたメールアドレスでユーザーを検索
        $user = User::where('email', $credentials['email'])->first();

        // ユーザーが存在しない、またはパスワードが一致しない場合のチェック
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            $route = $isAdminLogin ? 'admin.login' : 'login';
            return redirect()->route($route)
                ->withErrors([
                    // Fortifyのデフォルト動作に合わせて、認証失敗時のエラーは email に表示させるのが一般的
                    'email' => 'ログイン情報が登録されていません。',
                ])->onlyInput('email');
        }

        // 認証成功後のロールに基づく処理
        if ($isAdminLogin) {
            // 管理者ログインページからのリクエストの場合
            if ($user->role !== 'admin') {
                return redirect()->route('admin.login')
                    ->withErrors(['email' => 'ログイン情報が登録されていません。'])
                    ->onlyInput('email');
            }
            // 管理者として認証
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended('/admin/attendance/list');
        } else {
            // 通常ユーザーログインページからのリクエストの場合
            if ($user->role === 'admin') {
                return redirect()->route('login')
                    ->withErrors(['email' => 'ログイン情報が登録されていません。'])
                    ->onlyInput('email');
            }
            // 一般ユーザーとして認証
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended('/attendance');
        }
    }
}