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
        Schema::create('stp_students', function (Blueprint $table) {
            $table->id('id');
            $table->string('student_userName');
            $table->string('student_password');
            $table->integer('student_icNumber')->unique()->nullable();
            $table->string('student_email')->unique()->nullable();
            $table->string('student_countryCode');
            $table->string('student_contactNo');
            $table->foreignId('user_role')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->string('student_profilePic')->nullable();
            $table->integer('student_status')->default(1);
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken()->nullable();
            $table->timestamps();

            // $table->foreign('user_role')->references('id')->on('stp_core_metas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_students');
    }
};
