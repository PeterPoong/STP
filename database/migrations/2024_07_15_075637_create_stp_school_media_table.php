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
        Schema::create('stp_school_media', function (Blueprint $table) {
            $table->id();
            $table->string('schoolMedia_name');
            $table->foreignId('school_id')->nullable()->constrained('stp_schools')->onDelete('set null');
            $table->string('schoolMedia_locaton');
            $table->foreignId('schoolMedia_type')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->string('schoolMedia_format');
            $table->integer('schoolMedia_status')->default(1);
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();



            // $table->foreign('school_id')->references('id')->on('stp_schools')->onDelete('set null');
            // $table->foreign('schoolMedia_type')->references('id')->on('stp_core_metas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_school_media');
    }
};
