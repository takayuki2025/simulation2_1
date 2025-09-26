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
            $table->dateTime('break_start_time_1')->nullable();
            $table->dateTime('break_end_time_1')->nullable();
            $table->dateTime('break_start_time_2')->nullable();
            $table->dateTime('break_end_time_2')->nullable();
            $table->dateTime('break_start_time_3')->nullable();
            $table->dateTime('break_end_time_3')->nullable();
            $table->dateTime('break_start_time_4')->nullable();
            $table->dateTime('break_end_time_4')->nullable();
            $table->integer('break_total_time')->default(0)->nullable();
            $table->integer('work_time')->nullable();
            $table->string('reason')->nullable();
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
