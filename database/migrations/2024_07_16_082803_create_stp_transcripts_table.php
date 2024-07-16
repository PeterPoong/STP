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
        Schema::create('stp_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained('stp_subjects')->onDelete('set null');
            $table->foreignId('transcript_grade')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->integer('transcript_marks');
            $table->foreignId('user_id')->nullable()->constrained('stp_users')->onDelete('set null');
            $table->integer('stp_status')->default(1);
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
        Schema::dropIfExists('stp_transcripts');
    }
};
