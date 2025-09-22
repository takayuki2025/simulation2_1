<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Login;

class LoginController extends Controller
{
    /**
     * ログインフォームを表示する
     */
    public function showLoginForm(Request $request)
    {
        // ログインフォームが管理者用か一般ユーザー用か判断する
        if ($request->routeIs('admin.login')) {
            return view('auth.admin_login');
        }

        return view('auth.login');
    }

    /**
     * 新規ユーザー登録を実行する
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 登録イベントを発火
        event(new Registered($user));

        // ログイン処理
        Auth::login($user);

        // 登録後のリダイレクト先
        return redirect()->route('verification.notice');
    }


    /**
     * ログイン処理を実行する
     */
    public function login(Request $request)
    {
        // バリデーション
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 入力されたメールアドレスでユーザーを検索
        $user = User::where('email', $credentials['email'])->first();

        // 認証試行
        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'email' => '入力された情報が一致しません。',
            ])->onlyInput('email');
        }

        // 認証成功後のセッション再生成
        $request->session()->regenerate();

        // 管理者ログインページからのリクエストか確認
        if ($request->routeIs('admin.login.post')) {
            // 管理者ユーザー以外はログインを拒否
            if ($user->role !== 'admin') {
                Auth::logout();
                return back()->withErrors([
                    'email' => '入力されたユーザーは管理者ではありません。',
                ])->onlyInput('email');
            }
            return redirect()->intended('/admin/attendance/list');
        } else {
            // 通常ユーザーログインページからのリクエストの場合
            // 管理者ユーザーはログインを拒否
            if ($user->role === 'admin') {
                Auth::logout();
                return back()->withErrors([
                    'email' => '入力された管理者ユーザーはログインできません。',
                ])->onlyInput('email');
            }
            return redirect()->intended('/attendance');
        }
    }

    /**
     * ログアウト処理を実行する
     */
    public function logout(Request $request)
    {

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}