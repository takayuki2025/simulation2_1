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
        // ログインフォームが管理者用か一般ユーザー用か、ルート名で判断する
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
        // バリデーションルールを定義
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // 新しいユーザーをデータベースに作成
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 登録イベントを発火（例えば、メール認証通知を送るなど）
        event(new Registered($user));

        // 登録したばかりのユーザーを自動的にログインさせる
        Auth::login($user);

        // 登録後のリダイレクト先をメール認証通知ページに設定
        return redirect()->route('verification.notice');
    }


    /**
     * ログイン処理を実行する
     * このメソッドは、通常ユーザーと管理者、両方のログイン処理を共通で扱っています。
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

        // 管理者ログインページからのリクエストか確認
        $isAdminLogin = $request->routeIs('admin.login.post');

        // ユーザーが存在しない、またはパスワードが一致しない場合はエラーを返す
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return redirect()->route($isAdminLogin ? 'admin.login' : 'login')
                ->withErrors([
                    'email' => '入力された情報が一致しません。',
                ])->onlyInput('email');
        }

        if ($isAdminLogin) {
            // 管理者ログインページからのリクエストの場合
            if ($user->role !== 'admin') {
                // ロールがadminではない場合はログインを拒否
                return redirect()->route('admin.login')
                    ->withErrors([
                        'email' => '入力されたユーザーは管理者ではありません。',
                    ])->onlyInput('email');
            }
            // 認証成功、セッション再生成、管理者ダッシュボードにリダイレクト
            $request->session()->regenerate();
            Auth::login($user);
            return redirect()->intended('/admin/attendance/list');
        } else {
            // 通常ユーザーログインページからのリクエストの場合
            if ($user->role === 'admin') {
                // ロールがadminの場合はログインを拒否
                return redirect()->route('login')
                    ->withErrors([
                        'email' => '入力された管理者ユーザーはログインできません。',
                    ])->onlyInput('email');
            }
            // 認証成功、セッション再生成、通常ユーザーダッシュボードにリダイレクト
            $request->session()->regenerate();
            Auth::login($user);
            return redirect()->intended('/attendance');
        }
    }

    /**
     * ログアウト処理を実行する
     * このメソッドはコメントアウトされています。
     * Fortifyを使用する場合、ログアウト処理はFortifyの機能に任せるのが一般的です。
     * Fortifyは、AuthenticatedSessionController::destroyメソッドでログアウトを処理し、
     * 自動的にリダイレクトを行います。
     */
    // public function logout(Request $request)
    // {
    //     Auth::logout();
    //     $request->session()->invalidate();
    //     $request->session()->regenerateToken();

    //     return redirect('/login');
    // }
}