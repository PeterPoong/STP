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
        Schema::create('stp_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('stp_schools')->onDelete('set null');
            $table->string('course_name');
            $table->string('course_description');
            $table->string('course_requirement');
            $table->decimal('course_cost');
            $table->string('course_period');
            $table->string('course_intake');
            $table->foreignId('category_id')->nullable()->constrained('stp_courses_categories')->onDelete('set null');
            $table->foreignId('qualification_id')->nullable()->constrained('stp_qualifications')->onDelete('set null');
            $table->string('course_logo')->nullable();
            $table->integer('course_status')->default(1);
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
        Schema::dropIfExists('stp_courses');
    }
};
