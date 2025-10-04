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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->dateTime('clock_in_time');
            $table->dateTime('clock_out_time');
            $table->json('break_time')->nullable();
            $table->string('reason', 191);
            $table->boolean('pending')->default(true);
            $table->timestamps();
        });
    }

    /**
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
