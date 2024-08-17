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
        Schema::create('stp_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained('stp_courses')->onDelete('set null');
            $table->foreignId('intake_month')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->integer('intake_status')->default(1);
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
        Schema::dropIfExists('stp_intakes');
    }
};
