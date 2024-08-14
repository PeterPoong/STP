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
        Schema::create('stp_student_media', function (Blueprint $table) {
            $table->id();
            $table->string('studentMedia_name');
            $table->foreignId('student_id')->nullable()->constrained('stp_students')->onDelete('set null');
            $table->string('studentMedia_location');
            $table->foreignId('studentMedia_type')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->string('studentMedia_format')->nullable();
            $table->integer('studentMedia_status')->default(1);
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();

            // $table->foreign('student_id')->references('id')->on('stp_students')->onDelete('set null');
            // $table->foreign('studentMedia_type')->references('id')->on('stp_core_metas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_student_media');
    }
};
