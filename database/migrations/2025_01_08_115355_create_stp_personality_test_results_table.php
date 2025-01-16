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
        Schema::create('stp_personality_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->string('score');
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_personality_test_results');
    }
};
