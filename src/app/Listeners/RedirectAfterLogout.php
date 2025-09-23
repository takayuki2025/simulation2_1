<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

class RedirectAfterLogout
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        // ログアウト後に指定のルートへリダイレクト
        // ここでは、ログアウト後にログインページへリダイレクトします。
        // デフォルトでは、アプリケーションのホームルート（/）にリダイレクトされます。
        // 必要に応じて、他のルートに変更してください。
        Redirect::to(route('login'))->send();
    }
}