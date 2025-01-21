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
        Schema::create('stp_riasec_result_images', function (Blueprint $table) {
            $table->id();
            $table->string('resultImage_location');
            $table->foreignId('riasec_imageType')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_riasec_result_images');
    }
};
