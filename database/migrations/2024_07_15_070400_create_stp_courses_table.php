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
            $table->string('course_name');
            $table->string('course_description');
            $table->string('course_requirement');
            $table->foreignId('schoolMedia_type')->nullable()->constrained('stp_schools')->onDelete('set null');
            $table->decimal('course_cost');
            $table->string('cost_period'); // Corrected the column name
            $table->date('course_intake');
            $table->foreignId('course_category')->nullable()->constrained('stp_courses_categories')->onDelete('set null');
            $table->foreignId('course_qualification')->nullable()->constrained('stp_qualifications')->onDelete('set null');
            $table->string('course_logo')->nullable();
            $table->integer('course_status')->default(1);
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();

            // $table->foreign('school_id')->references('id')->on('stp_schools')->onDelete('set null');
            // $table->foreign('course_category')->references('id')->on('stp_core_metas')->onDelete('set null');
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
