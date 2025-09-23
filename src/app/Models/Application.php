<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{



            protected $fillable = [
        'user_id',
        'attendance_id',
        'checkin_date',
        'clock_in_time',
        'clock_out_time',
        'break_start_time_1',
        'break_end_time_1',
        'break_start_time_2',
        'break_end_time_2',
        'break_start_time_3',
        'break_end_time_3',
        'break_start_time_4',
        'break_end_time_4',
        'break_total_time',
        'work_time',
        'pending',
    ];






}
