<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * このファクトリーに対応するモデル名。
     *
     * @var string
     */
    protected $model = Attendance::class;

    /**
     * モデルのデフォルト状態を定義します。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 1. 出勤時刻と日付を定義
        // 過去1週間以内のランダムな日時を設定
        $clockIn = $this->faker->dateTimeBetween('-1 week', 'yesterday');
        $checkinDate = $clockIn->format('Y-m-d');

        // 2. 退勤時刻を、出勤から約8時間後のランダムな時刻として定義
        $clockOut = $this->faker->dateTimeBetween($clockIn->format('Y-m-d H:i:s'), $clockIn->format('Y-m-d H:i:s') . ' +10 hours');

        // 3. 休憩時間（ここでは単純に1時間=60分と仮定）
        $breakStart = (clone $clockIn)->modify('+4 hours');
        $breakEnd = (clone $breakStart)->modify('+1 hour');
        $breakTotalMinutes = 60; // 休憩合計時間（分）

        // 4. JSON形式の break_time データを構築
        $breakTimeArray = [
            [
                'start' => $breakStart->format('Y-m-d H:i:s'),
                'end' => $breakEnd->format('Y-m-d H:i:s'),
            ]
        ];

        // 5. 勤務時間の計算
        // (退勤時刻 - 出勤時刻) - 休憩時間 (秒から分に変換)
        $durationSeconds = $clockOut->getTimestamp() - $clockIn->getTimestamp();
        $workTimeMinutes = round(($durationSeconds / 60) - $breakTotalMinutes);

        return [
            // Userモデルと連携（UserFactoryが必要です）
            'user_id' => \App\Models\User::factory(),
            'checkin_date' => $checkinDate,
            'clock_in_time' => $clockIn->format('Y-m-d H:i:s'),
            'clock_out_time' => $clockOut->format('Y-m-d H:i:s'),
            
            // JSONカラムとして保存するために配列をエンコード
            'break_time' => json_encode($breakTimeArray),
            'break_total_time' => $breakTotalMinutes,
            'work_time' => $workTimeMinutes,
            
            // 修正済み: 理由欄は常にnullとする
            'reason' => null,
            
            'created_at' => $clockIn,
            'updated_at' => $clockOut,
        ];
    }
}