<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasOne;

class Attendance extends Model
{
    use HasFactory;

        protected $fillable = [
        'user_id',
        'checkin_date',
        'clock_in_time',
        'clock_out_time',
        // カラム名を 'break_time' に変更
        'break_time',
        'break_total_time',
        'work_time',
        'reason',
    ];

    /**
     * JSONカラムをPHPの配列として扱うよう設定
     */
    protected $casts = [
        // カラム名を 'break_time' に変更
        'break_time' => 'array',
        'clock_in_time' => 'datetime',
        'clock_out_time' => 'datetime',
        // 'checkin_date' => 'date',
    ];

        public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


        public function application(): HasOne
    {
        return $this->hasOne(Application::class, 'attendance_id');
    }
}

