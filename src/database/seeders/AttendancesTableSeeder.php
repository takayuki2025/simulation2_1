<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

class AttendancesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // シードするユーザーIDの配列
        $userIds = [2, 3, 4, 5];
        // 過去62日間のデータを生成（今日の分は含めない）
        $daysToSeed = 62;

        // シードデータの最も古い日付（約62日前）を基準日とし、その週の月曜日を開始点とする。
        $startDate = Carbon::today()->subDays($daysToSeed);
        $startReferenceWeek = $startDate->startOfWeek(Carbon::MONDAY); 

        foreach ($userIds as $userId) {

            // 各ユーザーに対して、昨日から過去62日分のデータを生成
            for ($i = 1; $i <= $daysToSeed; $i++) {
                // 日付を過去にずらす
                $date = Carbon::today()->subDays($i);

                // 土日をスキップ
                if ($date->isSaturday() || $date->isSunday()) {
                    continue;
                }

                // 平日にランダムで休みを取る（約7%の確率でスキップ）
                if (rand(1, 100) <= 7) {
                    continue;
                }

                // ユーザーIDに応じて基本の出退勤時刻を設定 (デフォルトは日勤)
                $baseClockInHour = 9;
                $baseClockOutHour = 18;
                $isNightShift = false; // 夜勤フラグ

                if ($userId == 4) {
                    // ユーザーID 4 のみ基本出勤時間を21時、基本退勤時間を翌日の6時に設定 (固定夜勤)
                    $baseClockInHour = 21;
                    $baseClockOutHour = 6;
                    $isNightShift = true;
                } elseif ($userId == 5) {

                    // 【ユーザーID 5 のシフト調整ロジック】
                    // $i は過去からの経過日数ではなく、カレンダー上の週（月曜始まり）に基づいてシフトを切り替える。
                    // 現在の日付が属する週の始まり（月曜日）を取得
                    $currentWeekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
                    // 基準週の始まりから現在の週の始まりまでの週インデックスを計算
                    // これにより、平日/週末に関係なくカレンダー週単位でシフトが切り替わる
                    $weekIndexByDate = $currentWeekStart->diffInWeeks($startReferenceWeek);

                    if ($weekIndexByDate % 2 === 0) {
                        // 偶数週 (基準日から0, 2, 4...週目) は日勤: 9:00 - 18:00
                        $baseClockInHour = 9;
                        $baseClockOutHour = 18;
                        $isNightShift = false;
                    } else {
                        // 奇数週 (基準日から1, 3, 5...週目) は夜勤: 21:00 - 6:00 (翌日)
                        $baseClockInHour = 21;
                        $baseClockOutHour = 6;
                        $isNightShift = true;
                    }
                }

                // 基準時刻を $baseClockInHour:00 に設定し、-15分～0分の間で変動を加え、必ず基準時刻までに出勤させる
                // 例：日勤は 8:45-9:00、夜勤は 20:45-21:00
                $clockInTime = $date->copy()->setHour($baseClockInHour)->setMinute(0)->setSecond(0)->addMinutes(rand(-15, 0));

                // 約30%の確率で残業を発生させるフラグを設定
                $hasOvertime = rand(1, 100) <= 30;
                $overtimeMinutes = 0;
                // 退勤後のランダム変動は0分～+15分に固定（定時より早く帰らないため）
                $randomMinutesVariation = rand(0, 15);
                // 基本退勤時刻のCarbonインスタンスを作成
                $baseClockOutTime = $date->copy()->setHour($baseClockOutHour)->setMinute(0)->setSecond(0);

                // 夜勤の場合（$isNightShift が true の場合）は翌日の日付にする
                if ($isNightShift) {
                    $baseClockOutTime->addDay();
                }

                if ($hasOvertime) {
                    // 残業ありの場合 (15分～120分、つまり2時間以内)
                    // 修正: 最低残業時間を15分に設定
                    $overtimeMinutes = rand(15, 120);
                    // 退勤時間: 基本退勤時刻 + 残業時間 + ランダム変動 (0～15分)
                    $clockOutTime = $baseClockOutTime->copy()->addMinutes($overtimeMinutes + $randomMinutesVariation);
                } else {
                    // 残業なしの場合 (基本退勤時刻～+15分の間で退勤)
                    $clockOutTime = $baseClockOutTime->copy()->addMinutes($randomMinutesVariation);
                }

                $totalBreakMinutes = 0;
                $breakTimeJsonArray = [];

                // 休憩時間の基準時刻を夜勤フラグに合わせて調整
                $lunchBaseHour = $isNightShift ? 23 : 12; // 昼休憩（夜勤の場合は23:00頃）
                $smallBreak1BaseHour = $isNightShift ? 2 : 15; // 午後の小休憩1（夜勤の場合は2:00頃）
                $smallBreak2BaseHour = $isNightShift ? 4 : 16; // 午後の小休憩2（夜勤の場合は4:00頃）

                // 1. 昼休憩（45分〜50分に調整）
                // 定時日の休憩合計を55分～60分にするため、昼休憩の最小を45分に引き上げ
                $lunchStart = $date->copy()->setHour($lunchBaseHour)->setMinute(0)->addMinutes(rand(-5, 5));
                // 夜勤で23時休憩の場合、日付は $dateのまま（出勤日と同じ日）

                $lunchDuration = rand(45, 50); // 45分～50分に調整
                $lunchEnd = $lunchStart->copy()->addMinutes($lunchDuration);
                $breakTimeJsonArray[] = ['start' => $lunchStart->toDateTimeString(), 'end' => $lunchEnd->toDateTimeString()];
                $totalBreakMinutes += $lunchDuration; // total: 45分～50分

                // 2. 午後の小休憩1（5分〜10分）
                $smallBreak1Start = $date->copy()->setHour($smallBreak1BaseHour)->setMinute(0)->addMinutes(rand(-5, 5));
                if ($isNightShift && $smallBreak1BaseHour == 2) {
                     // 2:00休憩の場合、日付を翌日に調整 (21時より時間が小さい)
                    if ($smallBreak1Start->hour < $baseClockInHour) {
                        $smallBreak1Start->addDay();
                    }
                }
                $smallBreak1Duration = rand(5, 10); // 5分～10分
                $smallBreak1End = $smallBreak1Start->copy()->addMinutes($smallBreak1Duration);
                $breakTimeJsonArray[] = ['start' => $smallBreak1Start->toDateTimeString(), 'end' => $smallBreak1End->toDateTimeString()];
                $totalBreakMinutes += $smallBreak1Duration; // total: 50分～60分

                // 3. 午後の小休憩2休憩合計を55分～60分の範囲に収めるように調整
                // 55分に到達するために必要な最低時間
                $minRequired = max(0, 55 - $totalBreakMinutes);
                // 60分を超えないための最大許容時間（かつ最大10分）
                $maxAllowed = min(10, 60 - $totalBreakMinutes);

                $smallBreak2Duration = 0;

                // minRequiredとmaxAllowedの間にランダムな時間を設定（合計55分～60分になる）
                if ($maxAllowed >= $minRequired) {
                    $smallBreak2Duration = rand($minRequired, $maxAllowed);
                }

                if ($smallBreak2Duration > 0) {
                    $smallBreak2Start = $date->copy()->setHour($smallBreak2BaseHour)->setMinute(0)->addMinutes(rand(-5, 5));
                    if ($isNightShift && $smallBreak2BaseHour == 4) {
                         // 4:00休憩の場合、日付を翌日に調整 (21時より時間が小さい)
                        if ($smallBreak2Start->hour < $baseClockInHour) {
                            $smallBreak2Start->addDay();
                        }
                    }
                    $smallBreak2End = $smallBreak2Start->copy()->addMinutes($smallBreak2Duration);
                    $breakTimeJsonArray[] = ['start' => $smallBreak2Start->toDateTimeString(), 'end' => $smallBreak2End->toDateTimeString()];
                    $totalBreakMinutes += $smallBreak2Duration;
                }
                // ※この時点で、定時日の休憩合計は55分～60分に確定
                // 残業が発生した場合、基本退勤時刻から10分間の休憩を確実に追加
                if ($hasOvertime) {
                    $overtimeBreakStart = $baseClockOutTime->copy(); // 基本退勤時刻から開始
                    $overtimeBreakEnd = $overtimeBreakStart->copy()->addMinutes(10); // 10分間の休憩

                    $breakTimeJsonArray[] = [
                        'start' => $overtimeBreakStart->toDateTimeString(),
                        'end' => $overtimeBreakEnd->toDateTimeString()
                    ];
                    $totalBreakMinutes += 10; // 総休憩時間に10分を追加
                }
                // ※この時点で、残業日の休憩合計は65分～70分に確定 (55分+10分～60分+10分)
                // 勤務時間（休憩時間を引く前の総拘束時間）を分単位で計算
                $totalElapsedMinutes = abs($clockOutTime->diffInMinutes($clockInTime));

                // 最終的な実労働時間 = 総拘束時間 - 総休憩時間
                $finalWorkMinutes = max(0, $totalElapsedMinutes - $totalBreakMinutes);


                // Attendanceレコードを作成
                Attendance::create([
                    'user_id' => $userId,
                    'clock_in_time' => $clockInTime,
                    'clock_out_time' => $clockOutTime,
                    'checkin_date' => $date->toDateString(),
                    'work_time' => $finalWorkMinutes,
                    'break_total_time' => $totalBreakMinutes,
                    // JSONカラムに配列を渡す
                    'break_time' => $breakTimeJsonArray,
                ]);
            }
        }
    }
}