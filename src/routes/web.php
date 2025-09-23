<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\LoginController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
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

// ゲストユーザー向けの認証ルート
// ログイン済みユーザーはアクセスできません。
Route::middleware(['guest'])->group(function () {
    // 通常ユーザーログイン
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');

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

    Route::post('/email/verification-notification', function (Request $request) {
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



    // ログアウト
    // Route::post('/logout', function (Request $request) {
    //     auth()->guard('web')->logout();
    //     $request->session()->invalidate();
    //     $request->session()->regenerateToken();
    //     return redirect('/login');
    // })->name('logout');
});
        // ログアウトはFortifyに任せます。
    // Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
// 通常のトップページなど、認証を必要としないルートはここに配置できます。
// Route::get('/', function () {
//     return view('auth.login');
// });