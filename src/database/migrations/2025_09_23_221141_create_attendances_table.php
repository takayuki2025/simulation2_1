<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date')->nullable();
            $table->dateTime('clock_in_time')->nullable();
            $table->dateTime('clock_out_time')->nullable();
            // カラム名を 'break_time' に変更し、休憩開始・終了のペアの配列を格納します
            // 例: [{"start": "2025-01-01 12:00:00", "end": "2025-01-01 13:00:00"}, ...]
            $table->json('break_time')->nullable();
            $table->integer('break_total_time')->default(0)->nullable();
            $table->integer('work_time')->nullable();
            $table->string('reason', 191)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
