<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

/**
 * 勤怠修正申請のバリデーションテスト (ID11)
 * 休憩時間、勤務時間の境界条件、および必須項目を検証します。
 */
class Id11Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 認証済みのユーザーを作成
        $this->user = User::factory()->create();

        // テスト用の申請先URL
        $this->postRoute = route('application.create');
        
        // 成功するリクエストのベースデータ (勤務時間内での適切な休憩)
        $this->validData = [
            'attendance_id' => null,
            'user_id' => $this->user->id,
            'checkin_date' => '2023-10-27',
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'reason' => 'テストのための修正理由です。',
            'break_times' => [
                ['start_time' => '12:00', 'end_time' => '13:00'],
            ],
        ];
    }

    /**
     * 【追加検証 1】必須フィールド（出勤時刻、退勤時刻、備考）の欠落をチェック。
     * メッセージ: required, reason.required
     */
    public function test_required_fields_check()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '';
        $invalidData['clock_out_time'] = '';
        $invalidData['reason'] = '';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'clock_in_time' => '出勤時刻を入力してください。',
            'clock_out_time' => '退勤時刻を入力してください。',
            'reason' => '備考を記入してください。',
        ]);
    }

    /**
     * 【追加検証 2】出勤時刻が退勤時刻より後になっている順序エラーをチェック。
     * ルール: clock_in_time.before:clock_out_time
     * メッセージ: 出勤時刻が不適切な値です。
     */
    public function test_clock_in_after_clock_out_fails()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '19:00'; // 18:00より後
        $invalidData['clock_out_time'] = '18:00';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'clock_in_time' => '出勤時刻が不適切な値です。',
        ]);
    }

    /**
     * 【追加検証 3】退勤時刻が出勤時刻より前になっている順序エラーをチェック。
     * ルール: clock_out_time.after:clock_in_time
     * メッセージ: 退勤時間が不適切な値です。
     */
    public function test_clock_out_before_clock_in_fails()
    {
        $invalidData = $this->validData;
        $invalidData['clock_in_time'] = '09:00';
        $invalidData['clock_out_time'] = '08:00'; // 09:00より前

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'clock_out_time' => '退勤時間が不適切な値です。',
        ]);
    }


    // ----------------------------------------------------
    // 以下は前回のテストで作成済みのロジックです
    // ----------------------------------------------------

    /**
     * 【検証 4】休憩開始時刻が退勤時刻より後に入力された場合。
     */
    public function test_break_start_after_clock_out_fails()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['start_time'] = '19:00';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'break_times.0.start_time' => '休憩時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 5】休憩終了時刻が出勤時刻より前に入力された場合。
     */
    public function test_break_end_before_clock_in_fails()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['end_time'] = '08:00';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'break_times.0.end_time' => '休憩時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 6】休憩時間が逆転して入力された場合。
     */
    public function test_break_times_are_reversed_fails()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['start_time'] = '14:00';
        $invalidData['break_times'][0]['end_time'] = '13:00';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'break_times.0.start_time' => '休憩時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 7】休憩開始時刻が出勤時刻より前に入力された場合 (after_or_equalテスト)。
     */
    public function test_break_start_before_or_at_clock_in_boundary_check()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['start_time'] = '08:00';
        $invalidData['break_times'][0]['end_time'] = '08:30';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'break_times.0.start_time' => '休憩開始時刻は、出勤時刻以降に設定してください。',
        ]);
    }

    /**
     * 【検証 8】休憩終了時刻が退勤時刻より後に入力された場合 (before_or_equalテスト)。
     */
    public function test_break_end_after_or_at_clock_out_boundary_check()
    {
        $invalidData = $this->validData;
        $invalidData['break_times'][0]['start_time'] = '18:00';
        $invalidData['break_times'][0]['end_time'] = '18:30';

        $response = $this->actingAs($this->user)->post($this->postRoute, $invalidData);

        $response->assertSessionHasErrors([
            'break_times.0.end_time' => '休憩時間もしくは退勤時間が不適切な値です。',
        ]);
    }

    /**
     * 【検証 9】全ての時刻が正しい場合（成功ケース）。
     */
    public function test_valid_data_passes_validation()
    {
        $response = $this->actingAs($this->user)->post($this->postRoute, $this->validData);

        $response->assertSessionHasNoErrors();
    }
}