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
        Schema::create('stp_school_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('stp_schools')->onDelete('set null');
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
        Schema::dropIfExists('stp_school_otps');
    }
};
