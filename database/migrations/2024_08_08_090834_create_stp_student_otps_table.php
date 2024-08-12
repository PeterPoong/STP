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
        Schema::create('stp_student_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->integer('otp');
            $table->datetime('otp_expired_time');
            $table->integer('otp_status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_student_otps');
    }
};
