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
            $hasClockTimeFormatError = $validator->errors()->hasAny(['clock_in_time', 'clock_out_time']);

            if (!$hasClockTimeFormatError) {
                $clockInCarbon = Carbon::parse($date . ' ' . $checkinTime);
                $clockOutCarbon = Carbon::parse($date . ' ' . $checkoutTime);
                if ($clockOutCarbon->lt($clockInCarbon)) {
                    $clockOutCarbonForToday = $clockOutCarbon;
                    $clockOutCarbonForNextDay = $clockOutCarbonForToday->copy()->addDay();
                    $duration = $clockInCarbon->diffInHours($clockOutCarbonForNextDay);

                    if ($duration >= 18) {
                        $validator->errors()->add('clock_in_time', $this->messages()['clock_in_time.before']);
                        $validator->errors()->add('clock_out_time', $this->messages()['clock_out_time.after']);
                    } else {
                        $clockOutCarbon = $clockOutCarbonForNextDay;
                        $isCrossDayShift = true;
                    }
                } else {
                    if ($clockInCarbon->gt($clockOutCarbon)) {
                        $validator->errors()->add('clock_in_time', $this->messages()['clock_in_time.before']);
                        $validator->errors()->add('clock_out_time', $this->messages()['clock_out_time.after']);
                    }
                }
            }
            foreach ($breakTimes as $index => $breakTime) {
                $breakStartTime = $breakTime['start_time'] ?? null;
                $breakEndTime = $breakTime['end_time'] ?? null;
                $hasStartTime = !empty($breakStartTime);
                $hasEndTime = !empty($breakEndTime);
                $hasBreakFormatError = $validator->errors()->has("break_times.{$index}.start_time") ||
                                        $validator->errors()->has("break_times.{$index}.end_time");
                if ($hasStartTime XOR $hasEndTime) {
                    if (!$hasStartTime) {
                        $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.required_with']);
                    }
                    if (!$hasEndTime) {
                        $validator->errors()->add("break_times.{$index}.end_time", $this->messages()['break_times.*.end_time.required_with']);
                    }
                }
                if (!$hasStartTime && !$hasEndTime) {
                    continue;
                }

                if ($hasBreakFormatError) {
                    continue;
                }

                if ($hasClockTimeFormatError) {
                    continue;
                }

                $breakStartCarbon = $hasStartTime ? Carbon::parse($date . ' ' . $breakStartTime) : null;
                $breakEndCarbon = $hasEndTime ? Carbon::parse($date . ' ' . $breakEndTime) : null;

                if ($isCrossDayShift) {

                    if ($breakStartCarbon && $breakStartCarbon->lt($clockInCarbon) && $breakStartCarbon->isSameDay($clockInCarbon)) {
                        $breakStartCarbon = $breakStartCarbon->addDay();
                    }

                    if ($breakEndCarbon && $breakEndCarbon->lt($clockInCarbon) && $breakEndCarbon->isSameDay($clockInCarbon)) {
                        $breakEndCarbon = $breakEndCarbon->addDay();
                    }
                }

                if ($breakStartCarbon && $breakEndCarbon && $breakEndCarbon->lte($breakStartCarbon)) {
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.before']);
                }

                if ($breakStartCarbon && $breakStartCarbon->lt($clockInCarbon)) {
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.after_or_equal']); 
                }

                if ($breakStartCarbon && $breakStartCarbon->gte($clockOutCarbon)) {
                    $validator->errors()->add("break_times.{$index}.start_time", $this->messages()['break_times.*.start_time.before']);
                }

                if ($breakEndCarbon && $breakEndCarbon->gt($clockOutCarbon)) {
                    $validator->errors()->add("break_times.{$index}.end_time", $this->messages()['break_times.*.end_time.before_or_equal']);
                }

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

        return [
            'clock_in_time.before' => '出勤時間もしくは退勤時間が不適切な値です。',
            'clock_out_time.after' => '出勤時間もしくは退勤時間が不適切な値です。',
            'break_times.*.start_time.before' => '休憩時間が不適切な値です。',
            'break_times.*.end_time.after' => '休憩時間が不適切な値です。',
            'break_times.*.start_time.after_or_equal' => '休憩時間が不適切な値です。',
            'break_times.*.end_time.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です。',
            'break_times.*.start_time.required_with' => '休憩開始時刻を入力してください。',    //
            'break_times.*.end_time.required_with' => '休憩終了時刻を入力してください。',      //
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