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
        Schema::create('stp_featureds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained('stp_courses')->onDelete('set null');
            $table->foreignId('school_id')->nullable()->constrained('stp_schools')->onDelete('set null');
            $table->dateTime('featured_startTime')->nullable();
            $table->dateTime('featured_endTime')->nullable();
            $table->foreignId('featured_type')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->integer('featured_status')->default(1);
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
        Schema::dropIfExists('stp_featureds');
    }
};
