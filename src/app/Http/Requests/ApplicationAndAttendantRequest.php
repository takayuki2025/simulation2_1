<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationAndAttendantRequest extends FormRequest
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
        // 修正: H:i の代わりに、柔軟な時刻形式を許容する正規表現を使用
        // (1-2桁の時):(2桁の分) または (2桁の時):(2桁の分) の形式を許容
        $timeRegex = '/^([0-9]{1,2}):([0-5][0-9])$/';

        return [
            // ...
            
            // 1. 出勤・退勤時間のバリデーション
            'clock_in_time' => [
                'required',
                'regex:' . $timeRegex, // 正規表現でチェック
                'before:clock_out_time', 
            ],
            'clock_out_time' => [
                'required',
                'regex:' . $timeRegex, // 正規表現でチェック
                'after:clock_in_time',
            ],

            // 2. 休憩時間（JSON配列）のバリデーション
            'break_times' => [
                'nullable',
                'array',
            ],
            
            'break_times.*.start_time' => [
                'required_with:break_times',
                'regex:' . $timeRegex, // 正規表現でチェック
                'before:break_times.*.end_time', 
                // 【追加】休憩開始時刻は出勤時刻と同時刻かそれ以降であること
                'after_or_equal:clock_in_time', 
            ],
            
            'break_times.*.end_time' => [
                'required_with:break_times',
                'regex:' . $timeRegex, // 正規表現でチェック
                'after:break_times.*.start_time',
                // 【追加】休憩終了時刻は退勤時刻と同時刻かそれ以前であること
                'before_or_equal:clock_out_time', 
            ],

            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        // メッセージをより具体的に修正・追加します。
        return [
            // ----------------------------------------------------
            // 1. 出勤・退勤の順序エラー
            'clock_in_time.before' => '出勤時刻は退勤時刻より前に設定してください。',
            'clock_out_time.after' => '退勤時刻は出勤時刻より後に設定してください。',

            'clock_in_time.required' => '出勤時刻を入力してください。',
            'clock_out_time.required' => '退勤時刻を入力してください。',

            // H:i 形式のチェックが失敗した場合のメッセージを追加 (regexのエラーとして表示されます)
            'clock_in_time.regex' => '出勤時刻は「H:i」または「HH:ii」の形式で入力してください。',
            'clock_out_time.regex' => '退勤時刻は「H:i」または「HH:ii」の形式で入力してください。',
            
            // ----------------------------------------------------
            // 2. 休憩時間のエラー
            
            // 各休憩開始時刻のエラー
            'break_times.*.start_time.before' => '休憩開始時刻は、終了時刻より前に設定してください。',
            'break_times.*.start_time.required_with' => '休憩開始時刻を入力してください。',
            'break_times.*.start_time.regex' => '休憩開始時刻は「H:i」または「HH:ii」の形式で入力してください。', 
            // 【追加】休憩開始時刻が早すぎる場合
            'break_times.*.start_time.after_or_equal' => '休憩開始時刻は、出勤時刻以降に設定してください。', 

            // 各休憩終了時刻のエラー
            'break_times.*.end_time.after' => '休憩終了時刻は、開始時刻より後に設定してください。',
            'break_times.*.end_time.required_with' => '休憩終了時刻を入力してください。',
            'break_times.*.end_time.regex' => '休憩終了時刻は「H:i」または「HH:ii」の形式で入力してください。',
            // 【追加】休憩終了時刻が遅すぎる場合
            'break_times.*.end_time.before_or_equal' => '休憩終了時刻は、退勤時刻以前に設定してください。',

            // ----------------------------------------------------
            // その他
            'checkin_date.required' => '申請日を入力してください。',
            'checkin_date.date_format' => '申請日の形式が正しくありません。',
            'reason.max' => '理由は500文字以内で入力してください。',
            'reason.required' => '理由を入力してください。',
        ];
    }

    /**
     * バリデーション属性名の日本語化
     * @return array
     */
    public function attributes()
    {
        $attributes = [
            'checkin_date' => '申請日',
            'clock_in_time' => '出勤時刻',
            'clock_out_time' => '退勤時刻',
            'reason' => '修正理由',
        ];

        // 休憩時間の属性名を動的に設定
        if ($this->has('break_times')) {
            foreach ($this->input('break_times') as $key => $value) {
                $num = $key + 1;
                $attributes["break_times.{$key}.start_time"] = "休憩{$num}の開始時刻";
                $attributes["break_times.{$key}.end_time"] = "休憩{$num}の終了時刻";
            }
        }

        return $attributes;
    }
}