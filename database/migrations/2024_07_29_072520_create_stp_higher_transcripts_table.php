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
        Schema::create('stp_higher_transcripts', function (Blueprint $table) {
            $table->id();
            $table->string('highTranscript_name');
            $table->foreignId('category_id')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->integer('highTranscript_status');
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
        Schema::dropIfExists('stp_higher_transcripts');
    }
};
