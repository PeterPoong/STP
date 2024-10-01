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
        Schema::create('stp_submited_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->foreignId('courses_id')->nullable()->constrained('stp_courses')->onDelete('set null');
            $table->string('form_feedback')->nullable();
            $table->integer('form_status')->default(1);
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_submited_forms');
    }
};
