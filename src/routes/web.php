<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;


use App\Http\Controllers\Auth\AttendantManagerController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;


use Laravel\Fortify\Fortify;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
// Fortifyが提供する認証ルートを自動的に設定します。
// Fortify::routes();

// `web`ミドルウェアグループに属するすべてのルートを定義します。
Route::group(['middleware' => 'web'], function () {

    // ゲストユーザー向けのルート
    // ログイン済みユーザーがアクセスしようとすると、自動的にホームページにリダイレクトされます。
    Route::middleware(['guest'])->group(function () {
        // ユーザー登録ルート
        // Fortifyのデフォルトビューにリダイレクト
        Route::get('/register', function () {
            return view('auth.register');
        })->name('register');
        Route::post('/register', [LoginController::class, 'register']);

        // 通常ログインルート
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login']);

        // パスワードリセットルート
        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

        // 管理者ログイン専用ルート
        Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('admin.login');
        Route::post('/admin/login', [LoginController::class, 'login'])->name('admin.login.post');
    });

    // ログイン済みのユーザーのみアクセスできるルート
    Route::middleware(['auth'])->group(function () {
        // メール認証ルート
        Route::get('/email/verify', function () {
            return view('auth.verify-email');
        })->middleware('auth')->name('verification.notice');

        Route::post('/email/verification-notification', function (Illuminate\Http\Request $request) {
            $request->user()->sendEmailVerificationNotification();
            return back()->with('status', 'verification-link-sent');
        })->middleware(['throttle:6,1'])->name('verification.send');

        Route::get('/email/verify/{id}/{hash}', function (Illuminate\Foundation\Auth\EmailVerificationRequest $request) {
            $request->fulfill();
            return redirect('/attendance');
        })->middleware(['signed'])->name('verification.verify');

        // 通常ユーザーのホーム (メール認証済みユーザーのみアクセス可能)
        Route::get('/attendance', function () {
            return view('user_stamping');
        })->middleware('verified')->name('attendance');

        // 管理者ユーザーの勤怠一覧ページ (管理者かつメール認証済みユーザーのみアクセス可能)
        Route::get('/admin/attendance/list', function () {
            return view('admin_attendance');
        })->middleware('admin')->name('admin.attendance.list');


    });

    // ログアウト
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // 制作できてる確認のためだけなので削除する
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
});