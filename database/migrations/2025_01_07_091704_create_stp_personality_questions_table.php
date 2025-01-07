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
        Schema::create('stp_personality_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->foreignId('riasec_type')->nullable()->constrained('stp_riasecTypes')->onDelete('set null');
            $table->integer('status')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_personality_questions');
    }
};
