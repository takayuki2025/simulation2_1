<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;
use Illuminate\Validation\Validator;

class ApplicationAndAttendantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // H:i または HH:ii の形式を許容
        $timeRegex = '/^([0-9]{1,2}):([0-5][0-9])$/';

        return [
            'checkin_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:500'],
            'break_times' => ['nullable', 'array'],

            'clock_in_time' => ['required', 'regex:' . $timeRegex],
            'clock_out_time' => ['required', 'regex:' . $timeRegex],
            
            'break_times.*.start_time' => ['required_with:break_times', 'regex:' . $timeRegex],
            'break_times.*.end_time' => ['required_with:break_times', 'regex:' . $timeRegex],
        ];
    }

    /**
     * Carbonを使用して日跨ぎを考慮した時刻の順序検証を追加する
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            // 形式チェックなどでエラーがある場合はスキップ
            if ($validator->errors()->hasAny(['clock_in_time', 'clock_out_time', 'break_times.*.start_time', 'break_times.*.end_time'])) {
                return;
            }

            $date = $this->input('checkin_date');
            $checkinTime = $this->input('clock_in_time');
            $checkoutTime = $this->input('clock_out_time');
            $breakTimes = $this->input('break_times', []);

            $clockInCarbon = Carbon::parse($date . ' ' . $checkinTime);
            $clockOutCarbon = Carbon::parse($date . ' ' . $checkoutTime);
            $isCrossDayShift = false;

            // 1. 【Id12Test 対策】出勤・退勤の順序チェック
            // 日跨ぎ出勤を判定する
            if ($clockOutCarbon->lt($clockInCarbon)) {
                $clockOutCarbonForToday = Carbon::parse($date . ' ' . $checkoutTime);
                
                // 1-a. 勤務時間の差を計算 (日跨ぎ前提の勤務時間)
                $clockOutCarbonForNextDay = $clockOutCarbonForToday->copy()->addDay();
                
                // 勤務開始から翌日の勤務終了までの時間差を計算
                $duration = $clockInCarbon->diffInHours($clockOutCarbonForNextDay);
                
                // 1-b. 勤務時間として現実的ではない長さ（18時間以上など）は単純な入力ミスとしてエラー
                // 例: 19:00 -> 18:00 (日跨ぎで23時間) は入力ミス
                // 例: 22:00 -> 06:00 (日跨ぎで8時間) はOK
                if ($duration >= 18) { 
                    $validator->errors()->add('clock_in_time', $this->messages()['clock_in_time.before']);
                    $validator->errors()->add('clock_out_time', $this->messages()['clock_out_time.after']);
                    return;
                }

                // 1-c. それ以外（例: 8時間シフト）は、日跨ぎとして補正を適用
                $clockOutCarbon = $clockOutCarbonForNextDay;
                $isCrossDayShift = true;
                
            } else {
                // 日跨ぎではない場合、単純に退勤時刻が出勤時刻より前であればエラー（例: 10:00 -> 09:00）
                if ($clockInCarbon->gt($clockOutCarbon)) {
                    $validator->errors()->add('clock_in_time', $this->messages()['clock_in_time.before']);
                    $validator->errors()->add('clock_out_time', $this->messages()['clock_out_time.after']);
                    return;
                }
            }

            // 2. 休憩時間の検証
            foreach ($breakTimes as $index => $breakTime) {
                $breakStartTime = $breakTime['start_time'] ?? null;
                $breakEndTime = $breakTime['end_time'] ?? null;

                if (empty($breakStartTime) || empty($breakEndTime)) {
                    continue; 
                }

                $breakStartCarbon = Carbon::parse($date . ' ' . $breakStartTime);
                $breakEndCarbon = Carbon::parse($date . ' ' . $breakEndTime);
                
                // 2-a. 【Failure 4 Fix / 休憩順序チェック】休憩開始 >= 休憩終了
                if ($breakEndCarbon->lte($breakStartCarbon)) {
                    // Failure 4 (reversed) は start_time に汎用メッセージを期待
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.before']);
                    // continue は削除し、他の検証も実行して Failure 5 (end_time) のエラーを捕捉できるようにする
                }
                
                // 2-b. 日跨ぎ休憩時刻補正 (日跨ぎシフトの場合、休憩時刻も翌日に補正)
                if ($isCrossDayShift) {
                    if ($breakStartCarbon->lt($clockInCarbon) && $breakStartCarbon->isSameDay($clockInCarbon)) {
                        $breakStartCarbon = $breakStartCarbon->addDay();
                    }
                    if ($breakEndCarbon->lt($breakStartCarbon)) {
                        $breakEndCarbon = $breakEndCarbon->addDay();
                    }
                }

                // 2-c. 【Rule 3-b / Failure 6 対策】休憩開始時刻が出勤時刻より前
                if ($breakStartCarbon->lt($clockInCarbon)) {
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.after_or_equal']); 
                }
                
                // 2-d. 【Rule 3-c / Failure 3 対策】休憩開始時刻が退勤時刻より後
                if ($breakStartCarbon->gte($clockOutCarbon)) {
                    // Failure 3 は start_time に汎用メッセージを期待
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.before']);
                }

                // 2-e. 【Rule 3-d / Failure 7 対策】休憩終了時刻が退勤時刻より後
                if ($breakEndCarbon->gt($clockOutCarbon)) {
                     // Failure 7 は end_time に特定メッセージを期待
                     $validator->errors()->add("break_times.{$index}.end_time", $this->messages()['break_times.*.end_time.before_or_equal']);
                }
                
                // 2-f. 【Rule 3-e / Failure 5 Fix】休憩終了時刻が出勤時刻より前
                if ($breakEndCarbon->lte($clockInCarbon)) {
                    // Failure 5 は end_time に汎用メッセージを期待
                    $validator->errors()->add("break_times.{$index}.end_time", $this->messages()['break_times.*.end_time.after']);
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            // ----------------------------------------------------
            // 1. 出勤・退勤の順序エラー
            'clock_in_time.before' => '出勤時刻が不適切な値です。', 
            'clock_out_time.after' => '退勤時間が不適切な値です。', 

            // ----------------------------------------------------
            // 2. 休憩時間のエラー
            // 汎用メッセージ
            'break_times.*.start_time.before' => '休憩時間が不適切な値です。',
            'break_times.*.end_time.after' => '休憩時間が不適切な値です。',   

            // 個別メッセージ
            'break_times.*.start_time.after_or_equal' => '休憩開始時刻は、出勤時刻以降に設定してください。', 
            'break_times.*.end_time.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です。', // Failure 7のみが期待する特定メッセージ

            // ----------------------------------------------------
            // 3. 形式・必須チェック
            'clock_in_time.required' => '出勤時刻を入力してください。',
            'clock_out_time.required' => '退勤時刻を入力してください。',
            // 他の必須・形式エラーメッセージ...
            'reason.required' => '備考を記入してください。',
            'checkin_date.required' => '対象日付は必須です。',
            'break_times.*.start_time.required_with' => '休憩開始時刻を入力してください。',
            'break_times.*.end_time.required_with' => '休憩終了時刻を入力してください。',
            'clock_in_time.regex' => '出勤時刻は「H:i」または「HH:ii」の形式で入力してください。',
            'clock_out_time.regex' => '退勤時刻は「H:i」または「HH:ii」の形式で入力してください。',
            'break_times.*.start_time.regex' => '休憩開始時刻は「H:i」または「HH:ii」の形式で入力してください。', 
            'break_times.*.end_time.regex' => '休憩終了時刻は「H:i」または「HH:ii」の形式で入力してください。',
            'reason.max' => '理由は500文字以内で入力してください。',
        ];
    }

    public function attributes()
    {
        $attributes = [
            'checkin_date' => '申請日',
            'clock_in_time' => '出勤時刻',
            'clock_out_time' => '退勤時刻',
            'reason' => '修正理由',
        ];

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