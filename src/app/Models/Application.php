<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    use HasFactory;


            protected $fillable = [
        'user_id',
        'attendance_id',
        'checkin_date',
        'clock_in_time',
        'clock_out_time',
        'break_time',
        'reason',
        'pending',
    ];

        protected $casts = [
        'pending' => 'boolean', // ← 明示しておくとDBの1/0がtrue/falseに変換される
        'break_time' => 'array',
        'clock_in_time' => 'datetime',
        'clock_out_time' => 'datetime',
    ];

        public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

        public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
