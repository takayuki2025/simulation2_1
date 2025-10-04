<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Application;
use Illuminate\Support\Carbon;

class ApplicationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // シードするユーザーIDの配列
        $userIds = [2, 3, 4, 5]; 
        // 過去31日間のデータを生成
        $daysToSeed = 31;

        foreach ($userIds as $userId) {
            // 各ユーザーに対して、今日から過去31日分のデータを生成
            for ($i = 0; $i < $daysToSeed; $i++) {
                // 日付を過去にずらす
                $date = Carbon::today()->subDays($i);

                // 平日の場合はスキップ（休日出勤申請のデータとしてシードするため）
                if ($date->isWeekday()) {
                    continue;
                }

                // 土日は20%の確率で出勤する
                if (rand(1, 100) > 20) {
                    continue;
                }

                // ランダムな変動幅を分単位で生成（-15分から+15分）
                $randomMinutes = rand(-15, 15);

                // 勤務開始時刻、勤務終了時刻を定義し、ランダムな変動を加える
                $clockInTime = $date->copy()->setHour(9)->setMinute(0)->setSecond(0)->addMinutes($randomMinutes);
                $clockOutTime = $date->copy()->setHour(18)->setMinute(0)->setSecond(0)->addMinutes($randomMinutes);

                // 休憩データを定義 (Carbonインスタンス)
                $breaks = [
                    // JSON内部キーを 'start' と 'end' に変更
                    ['start' => $date->copy()->setHour(12)->setMinute(0)->addMinutes(rand(-5, 5)), 'end' => $date->copy()->setHour(13)->setMinute(0)->addMinutes(rand(-5, 5))],
                    ['start' => $date->copy()->setHour(15)->setMinute(0)->addMinutes(rand(-5, 5)), 'end' => $date->copy()->setHour(15)->setMinute(15)->addMinutes(rand(-5, 5))],
                    ['start' => $date->copy()->setHour(16)->setMinute(0)->addMinutes(rand(-5, 5)), 'end' => $date->copy()->setHour(16)->setMinute(10)->addMinutes(rand(-5, 5))],
                ];

                // JSONカラム 'break_time' に格納するデータ配列を準備
                $breakTimeJsonArray = [];
                // 各休憩時間をJSONフォーマットに合わせて整形
                foreach ($breaks as $break) {
                    // start/end の Carbonインスタンスを文字列に変換して配列に追加
                    $breakTimeJsonArray[] = [
                        'start' => $break['start']->toDateTimeString(),
                        'end' => $break['end']->toDateTimeString(),
                    ];
                }

                // pendingカラムは50%の確率でfalse (承認済み) にする
                // rand(1, 100) が 50 以下なら false (承認済み)、それ以外なら true (保留中)
                $pending = (rand(1, 100) <= 0) ? false : true;

                    Application::create([
                        'user_id' => $userId,
                        // 'attendance_id' は nullable でここでは null のままにします
                        'clock_in_time' => $clockInTime,
                        'clock_out_time' => $clockOutTime,
                        'checkin_date' => $date->toDateString(),
                        // 'work_time' および 'break_total_time' はマイグレーションから削除されたため、ここから削除
                        'pending' => $pending,
                        'reason' => '休日出勤のため出勤',
                        // JSONカラムに配列を渡す
                        'break_time' => $breakTimeJsonArray,
                    ]);

            }
        }
    }
}