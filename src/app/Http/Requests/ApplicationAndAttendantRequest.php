<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;
use Illuminate\Validation\Validator;

class ApplicationAndAttendantRequest extends FormRequest
{
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
            'clock_in_time' => ['required', 'regex:' . $timeRegex],
            'clock_out_time' => ['required', 'regex:' . $timeRegex],
            'break_times.*.start_time' => ['nullable', 'regex:' . $timeRegex],
            'break_times.*.end_time' => ['nullable', 'regex:' . $timeRegex],
            'break_times' => ['nullable', 'array'],
            'reason' => ['required', 'string', 'max:191'],
        ];
    }
    /**
     * Carbonを使用して日跨ぎを考慮した時刻の順序検証を追加する
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $date = $this->input('checkin_date');
            $checkinTime = $this->input('clock_in_time');
            $checkoutTime = $this->input('clock_out_time');
            $breakTimes = $this->input('break_times', []);
            $clockInCarbon = null;
            $clockOutCarbon = null;
            $isCrossDayShift = false;
            // 1. 出勤・退勤の順序チェック (日跨ぎ判定を含む)
            // clock_in/out に形式エラーがないかチェック
            $hasClockTimeFormatError = $validator->errors()->hasAny(['clock_in_time', 'clock_out_time']);

            if (!$hasClockTimeFormatError) {
                // Carbonオブジェクトの生成
                $clockInCarbon = Carbon::parse($date . ' ' . $checkinTime);
                $clockOutCarbon = Carbon::parse($date . ' ' . $checkoutTime);
                // 出勤・退勤の順序チェック
                if ($clockOutCarbon->lt($clockInCarbon)) {
                    // 退勤が出勤より前の場合、日跨ぎの可能性をチェック
                    $clockOutCarbonForToday = $clockOutCarbon;
                    $clockOutCarbonForNextDay = $clockOutCarbonForToday->copy()->addDay();
                    $duration = $clockInCarbon->diffInHours($clockOutCarbonForNextDay);

                    if ($duration >= 18) {
                        // 異常な長時間勤務（不正な逆転）はエラー
                        $validator->errors()->add('clock_in_time', $this->messages()['clock_in_time.before']);
                        $validator->errors()->add('clock_out_time', $this->messages()['clock_out_time.after']);
                    } else {
                        // 正常な日跨ぎとして退勤時間を翌日に補正
                        $clockOutCarbon = $clockOutCarbonForNextDay;
                        $isCrossDayShift = true;
                    }
                } else {
                    if ($clockInCarbon->gt($clockOutCarbon)) {
                        // 日跨ぎではないが、退勤時刻が出勤時刻より前
                        $validator->errors()->add('clock_in_time', $this->messages()['clock_in_time.before']);
                        $validator->errors()->add('clock_out_time', $this->messages()['clock_out_time.after']);
                    }
                }
            }
            // 2. 休憩時間の検証
            foreach ($breakTimes as $index => $breakTime) {
                $breakStartTime = $breakTime['start_time'] ?? null;
                $breakEndTime = $breakTime['end_time'] ?? null;
                $hasStartTime = !empty($breakStartTime);
                $hasEndTime = !empty($breakEndTime);
                // 休憩時間の形式チェックエラー（このインデックスのみ）
                $hasBreakFormatError = $validator->errors()->has("break_times.{$index}.start_time") ||
                                        $validator->errors()->has("break_times.{$index}.end_time");
                // 2-a. 休憩の片方のみが入力された場合のチェック (必須チェック)
                if ($hasStartTime XOR $hasEndTime) {
                    if (!$hasStartTime) {
                        $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.required_with']);
                    }
                    if (!$hasEndTime) {
                        $validator->errors()->add("break_times.{$index}.end_time", $this->messages()['break_times.*.end_time.required_with']);
                    }
                    // ★ continue は削除し、必須エラーを追加しても、後の順序チェックを続行できるように変更
                }
                // 両方とも空欄の場合は、バリデーションをスキップ
                if (!$hasStartTime && !$hasEndTime) {
                    continue;
                }
                // ここから先は、少なくともどちらか一方が入力されている
                // 休憩時刻に形式エラーがある場合は、以降のCarbonに依存するチェックをスキップ
                if ($hasBreakFormatError) {
                    continue;
                }
                // 勤務時間の Carbon オブジェクトが形式エラーなどで作成されなかった場合は、
                // 勤務時間との境界チェック (2-d以降) はスキップ
                if ($hasClockTimeFormatError) {
                    continue;
                }
                // Carbon オブジェクトの生成 (入力がある場合のみ生成し、ない場合は null)
                $breakStartCarbon = $hasStartTime ? Carbon::parse($date . ' ' . $breakStartTime) : null;
                $breakEndCarbon = $hasEndTime ? Carbon::parse($date . ' ' . $breakEndTime) : null;
                // 2-c. 日跨ぎ休憩時刻補正 (境界チェックの前に適用)
                if ($isCrossDayShift) {
                    // 開始時刻の補正
                    if ($breakStartCarbon && $breakStartCarbon->lt($clockInCarbon) && $breakStartCarbon->isSameDay($clockInCarbon)) {
                        $breakStartCarbon = $breakStartCarbon->addDay();
                    }
                    // 終了時刻の補正
                    if ($breakEndCarbon && $breakEndCarbon->lt($clockInCarbon) && $breakEndCarbon->isSameDay($clockInCarbon)) {
                        $breakEndCarbon = $breakEndCarbon->addDay();
                    }
                }
                // 2-b. 休憩開始 >= 休憩終了 (休憩単体の順序チェック: 両方入力されている場合のみ)
                // 補正後の時刻でチェック
                if ($breakStartCarbon && $breakEndCarbon && $breakEndCarbon->lte($breakStartCarbon)) {
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.before']);
                }
                // 2-d. 休憩開始時刻が出勤時刻より前
                if ($breakStartCarbon && $breakStartCarbon->lt($clockInCarbon)) {
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.after_or_equal']); 
                }
                // 2-e. 休憩開始時刻が退勤時刻より後
                if ($breakStartCarbon && $breakStartCarbon->gte($clockOutCarbon)) {
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.before']);
                }
                // 2-f. 休憩終了時刻が退勤時刻より後
                if ($breakEndCarbon && $breakEndCarbon->gt($clockOutCarbon)) {
                    $validator->errors()->add("break_times.{$index}.end_time", $this->messages()['break_times.*.end_time.before_or_equal']);
                }
                // 2-g. 休憩終了時刻が出勤時刻より前
                if ($breakEndCarbon && $breakEndCarbon->lte($clockInCarbon)) {
                    $validator->errors()->add("break_times.{$index}.end_time", $this->messages()['break_times.*.end_time.after']);
                }
            }
        });
    }
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        // ログインユーザーが管理者 ('admin') であるかチェック (実際の認証ロジックに合わせて調整してください)
        $isAdmin = $this->user() && $this->user()->role === 'admin';
        // 管理者用の共通メッセージ
        $adminTimeError = '出勤時間もしくは退勤時間が不適切な値です。';
        // 一般ユーザー用の個別メッセージ
        $userClockInError = '出勤時刻が不適切な値です。';
        $userClockOutError = '退勤時間が不適切な値です。';
        return [
            // 1. 出勤・退勤の順序エラー (ロールによってメッセージを分岐)
            'clock_in_time.before' => $isAdmin ? $adminTimeError : $userClockInError,
            'clock_out_time.after' => $isAdmin ? $adminTimeError : $userClockOutError,
            // 2. 休憩時間のエラー (共通)
            // 汎用メッセージ
            'break_times.*.start_time.before' => '休憩時間が不適切な値です。',
            'break_times.*.end_time.after' => '休憩時間が不適切な値です。',
            // 個別メッセージ
            'break_times.*.start_time.after_or_equal' => '休憩開始時刻は、出勤時刻以降に設定してください。',
            'break_times.*.end_time.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です。',
            // 休憩の必須メッセージ (部分入力の場合にカスタムチェックで利用)
            'break_times.*.start_time.required_with' => '休憩開始時刻を入力してください。',    //
            'break_times.*.end_time.required_with' => '休憩終了時刻を入力してください。',      //
            // 3. 形式・必須チェック (共通)
            'clock_in_time.required' => '出勤時刻を入力してください。',
            'clock_out_time.required' => '退勤時刻を入力してください。',
            'reason.required' => '備考を記入してください。',
            'checkin_date.required' => '対象日付は必須です。',       //
            'clock_in_time.regex' => '出勤時刻は「H:i」または「HH:ii」の形式で入力してください。',
            'clock_out_time.regex' => '退勤時刻は「H:i」または「HH:ii」の形式で入力してください。',
            'break_times.*.start_time.regex' => '休憩開始時刻は「H:i」または「HH:ii」の形式で入力してください。',
            'break_times.*.end_time.regex' => '休憩終了時刻は「H:i」または「HH:ii」の形式で入力してください。',
            'reason.max' => '備考は191文字以内で入力してください。',
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