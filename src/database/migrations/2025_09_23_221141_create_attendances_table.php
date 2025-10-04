<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     *
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->dateTime('clock_in_time');
            $table->dateTime('clock_out_time')->nullable();
            $table->json('break_time')->nullable();
            $table->integer('break_total_time')->default(0);
            $table->integer('work_time')->nullable();
            $table->string('reason', 191)->nullable();
            $table->timestamps();
        });
    }

    /**
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
