<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Application;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

class ApplicationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // シードするユーザーIDの配列
        $userIds = [1, 2, 3];
        // 過去14日間のデータを生成
        $daysToSeed = 14;

        foreach ($userIds as $userId) {
            // 各ユーザーに対して、今日から過去14日分のデータを生成
            for ($i = 0; $i < $daysToSeed; $i++) {
                // 日付を過去にずらす
                $date = Carbon::today()->subDays($i);

                // 平日の場合はスキップ
                if ($date->isWeekday()) {
                    continue;
                }

                // 土日は35%の確率で出勤する
                if (rand(1, 100) > 35) {
                    continue;
                }

                // ランダムな変動幅を分単位で生成（-15分から+15分）
                $randomMinutes = rand(-15, 15);

                // 勤務開始時刻、勤務終了時刻を定義し、ランダムな変動を加える
                $clockInTime = $date->copy()->setHour(9)->setMinute(0)->setSecond(0)->addMinutes($randomMinutes);
                $clockOutTime = $date->copy()->setHour(18)->setMinute(0)->setSecond(0)->addMinutes($randomMinutes);

                // 勤務時間（休憩時間を引く前の時間）を分単位で計算
                $totalWorkMinutes = $clockOutTime->diffInMinutes($clockInTime);
                $totalBreakMinutes = 0;

                // 休憩データを定義 (最大4回)
                $breaks = [
                    ['start' => $date->copy()->setHour(12)->setMinute(0)->addMinutes(rand(-5, 5)), 'end' => $date->copy()->setHour(13)->setMinute(0)->addMinutes(rand(-5, 5))],
                    ['start' => $date->copy()->setHour(15)->setMinute(0)->addMinutes(rand(-5, 5)), 'end' => $date->copy()->setHour(15)->setMinute(15)->addMinutes(rand(-5, 5))],
                    ['start' => $date->copy()->setHour(16)->setMinute(0)->addMinutes(rand(-5, 5)), 'end' => $date->copy()->setHour(16)->setMinute(10)->addMinutes(rand(-5, 5))],
                ];

                $breakData = [];
                // 各休憩時間を合計し、配列に格納
                foreach ($breaks as $key => $break) {
                    $breakData["break_start_time_" . ($key + 1)] = $break['start'];
                    $breakData["break_end_time_" . ($key + 1)] = $break['end'];
                    $totalBreakMinutes += $break['end']->diffInMinutes($break['start']);
                }

                // 最終的な労働時間を計算
                $finalWorkMinutes = $totalWorkMinutes - $totalBreakMinutes;

                // pendingカラムは50%の確率でfalseにする
                $pending = (rand(1, 100) <= 50) ? false : true;

                // 既存のAttendanceレコードからランダムにIDを取得
                $attendance = Attendance::inRandomOrder()->first();

                // Attendanceレコードが存在する場合にのみApplicationレコードを作成
                if ($attendance) {
                    Application::create(array_merge([
                        'user_id' => $userId,
                        'attendance_id' => $attendance->id, // attendance_idを追加
                        'clock_in_time' => $clockInTime,
                        'clock_out_time' => $clockOutTime,
                        'checkin_date' => $date->toDateString(),
                        'work_time' => $finalWorkMinutes,
                        'break_total_time' => $totalBreakMinutes,
                        'pending' => $pending,
                        'reason' => '休日出勤のため出勤', // reasonカラムに文字列をセット
                    ], $breakData));
                }
            }
        }
    }
}