<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\LoginRequest;

class LoginController extends Controller
{

    public function showLoginForm(Request $request)
    {
        if ($request->routeIs('admin.login')) {
            return view('auth.admin-login');
        }

        return view('auth.login');
    }


    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $isAdminLogin = $request->isAdminLogin();
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            $route = $isAdminLogin ? 'admin.login' : 'login';
            return redirect()->route($route)
                ->withErrors([
                    'email' => 'ログイン情報が登録されていません。',
                ])->onlyInput('email');
        }

        if ($isAdminLogin) {
            if ($user->role !== 'admin') {
                return redirect()->route('admin.login')
                    ->withErrors(['email' => 'ログイン情報が登録されていません。'])
                    ->onlyInput('email');
            }
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended('/admin/attendance/list');
        } else {
            if ($user->role === 'admin') {
                return redirect()->route('login')
                    ->withErrors(['email' => 'ログイン情報が登録されていません。'])
                    ->onlyInput('email');
            }
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended('/attendance');
        }
    }
}