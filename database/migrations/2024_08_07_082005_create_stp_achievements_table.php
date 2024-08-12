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
        Schema::create('stp_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->string('achievement_name')->nullable();
            $table->foreignId('title_obtained')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->string('achivement_media')->nullable();
            $table->integer('date')->nullable();
            $table->integer('achievements_status')->default(1);
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
        Schema::dropIfExists('stp_achievements');
    }
};
