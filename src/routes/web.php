<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttendantManagerController;
// use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;


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

// 通常のトップページなど、認証を必要としないルート
// Route::get('/', function () {
//     return view('index');
// })->name('index');

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
    // Route::get('/attendance', function () {
    //     return view('user_stamping');
    // })->middleware('verified')->name('attendance.user.index');





    // 管理者ユーザーの勤怠一覧ページ (管理者かつメール認証済みユーザーのみアクセス可能)
    // Route::get('/admin/attendance/list', function () {
    //     return view('admin_attendance');
    // })->middleware('admin')->name('admin.attendance.list.index');

});
    // Route::get('/attendance', [AttendantManagerController::class, 'user_index'])->name('attendance.user.index');



// ユーザーの勤怠管理のルート
Route::middleware(['verified'])->group(function () {
    Route::get('/attendance', [AttendantManagerController::class, 'user_index'])->name('user.attendance.index');
    Route::post('/stamping/clock-in', [AttendantManagerController::class, 'clockIn'])->name('attendance.clock_in');
    Route::post('/stamping/clock-out', [AttendantManagerController::class, 'attendance_create'])->name('attendance.create');
    Route::post('/stamping/break-start', [AttendantManagerController::class, 'breakStart'])->name('attendance.break_start');
    Route::post('/stamping/break-end', [AttendantManagerController::class, 'breakEnd'])->name('attendance.break_end');
    Route::get('/attendance/list', [AttendantManagerController::class, 'user_list_index'])->name('user.attendance.list.index');
    Route::get('/attendance/detail/{id?}', [AttendantManagerController::class, 'user_attendance_detail_index'])->name('user.attendance.detail.index');
    Route::post('/attendance/update', [AttendantManagerController::class, 'application_create'])->name('application.create');
});

// 管理者の勤怠管理ルート
Route::middleware(['admin'])->group(function () {
    Route::get('/admin/attendance/list', [AttendantManagerController::class, 'admin_list_index'])->name('admin.attendance.list.index');
    Route::get('/admin/attendance/{id}', [AttendantManagerController::class, 'admin_user_attendance_detail_index'])->name('admin.user.attendance.detail.index');
    Route::post('/admin/attendance/approve', [AttendantManagerController::class, 'admin_attendance_approve'])->name('admin.attendance.approve');
    Route::get('/admin/staff/list', [AttendantManagerController::class, 'admin_staff_list_index'])->name('admin.staff.list.index');
    Route::get('/admin/attendance/staff/{id}', [AttendantManagerController::class, 'admin_staff_month_index'])->name('admin.staff.month.index');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AttendantManagerController::class, 'admin_apply_judgement_index'])->name('admin.apply.judgement.index');
    Route::post('/admin/apply/attendance/approve', [AttendantManagerController::class, 'admin_apply_attendance_approve'])->name('admin.apply.attendance.approve');



});

// 申請一覧共通ルート
Route::get('/stamp_correction_request/list', function (Request $request) {
    // ユーザーの`role`が`admin`かどうかをチェックします
    if ($request->user()->role === 'admin') {
        // ユーザーが管理者であれば、管理者のコントローラーを呼び出す
        return app(AttendantManagerController::class)->admin_apply_list_index($request);
    } else {
        // ユーザーが管理者でなければ、通常ユーザーのコントローラーを呼び出す
        return app(AttendantManagerController::class)->user_apply_index();
    }
})->middleware(['auth', 'verified'])->name('apply.list');