<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages()
    {
        // 一般ユーザー、管理者共通のメッセージ
        $messages = [
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => '有効なメールアドレス形式で入力してください。',
            'password.required' => 'パスワードを入力してください。',
        ];

        // ルート名が 'admin.login.post' の場合は、管理者ログイン用のメッセージを追加
        // このプロジェクトでは、ルートによってエラーメッセージを出し分ける必要は薄いですが、
        // 異なるバリデーションルールやメッセージを適用したい場合の例として残します。
        // if ($this->isAdminLogin()) {
        //     // 管理者ログイン特有のメッセージ
        // }

        return $messages;
    }


    public function isAdminLogin(): bool
    {
        return $this->routeIs('admin.login.post');
    }
}
