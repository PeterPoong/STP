<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PhpParser\Node\NullableType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stp_course_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->foreignId('course_id')->nullable()->constrained('stp_courses')->onDelete('set null');
            $table->integer('status')->default(1);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_course_interests');
    }
};
