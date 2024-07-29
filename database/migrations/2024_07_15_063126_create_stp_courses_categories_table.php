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
        Schema::create('stp_courses_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name');
            $table->string('category_description')->nullable();
            $table->string('category_icon')->nullable();
            $table->integer('course_hotPick')->nullable();
            $table->integer('category_status')->default(1);
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
        Schema::dropIfExists('stp_courses_categories');
    }
};
