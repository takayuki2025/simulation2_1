<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\User; // Userモデルも使用するためインポート
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * 対応するモデルを設定します。
     *
     * @var string
     */
    protected $model = Application::class;

    /**
     * モデルのデフォルト状態を定義します。
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // テストデータとして現実的な日付を生成
        $checkinDate = Carbon::instance($this->faker->dateTimeBetween('-1 year', 'now'))->toDateString();
        
        // 時刻データ
        $clockInTime = '09:00:00';
        $clockOutTime = '18:00:00';

        // 休憩データ（JSONとして保存される）
        $breakTimes = [
            ['start' => '12:00:00', 'end' => '13:00:00'],
        ];

        // 休憩合計時間 (分) - 1時間休憩を想定
        $breakTotalTime = 60;

        // 勤務時間 (分) - (18:00 - 09:00) = 9時間 (540分) - 休憩1時間 (60分) = 480分
        $workTime = 9 * 60 - $breakTotalTime;

        // ★FIX: マイグレーションのdateTime型に合わせて、日付と時刻を組み合わせた完全な日時文字列を生成
        $clockInDateTime = Carbon::parse($checkinDate . ' ' . $clockInTime)->toDateTimeString();
        $clockOutDateTime = Carbon::parse($checkinDate . ' ' . $clockOutTime)->toDateTimeString();

        return [
            // リレーション
            'user_id' => User::factory(), 
            // 勤怠日
            'checkin_date' => $checkinDate,
            
            // ★FIX: dateTime形式で保存
            'clock_in_time' => $clockInDateTime, 
            'clock_out_time' => $clockOutDateTime,
            
            // 休憩時間はJSON文字列として保存
            'break_time' => json_encode($breakTimes),
            
            // 休憩合計時間と勤務時間も設定（テストデータの整合性向上）
            'break_total_time' => $breakTotalTime,
            'work_time' => $workTime,

            // 備考
            'reason' => $this->faker->realText(50), 
            
            // ★FIX: boolean型に合わせて、true（文字列ではない）を設定する
            'pending' => true, 
        ];
    }
}