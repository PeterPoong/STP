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
        Schema::create('stp_student_details', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->string('student_detailFirstName')->nullable();
            $table->string('student_detailLastName')->nullable();
            $table->string('student_detailAddress')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('stp_countries')->onDelete('set null');
            $table->foreignId('state_id')->nullable()->constrained('stp_states')->onDelete('set null');
            $table->foreignId('city_id')->nullable()->constrained('stp_cities')->onDelete('set null');
            $table->string('student_detailPostcode')->nullable();
            $table->string('student_detailStatus')->default(1);
            $table->string('updated_by')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_student_details');
    }
};
