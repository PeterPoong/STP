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
        Schema::create('stp_school_social_media', function (Blueprint $table) {
            $table->id('id');
            // $table->unsignedBigInteger('schoolSocialMedia_type')->nullable();
            $table->foreignId('schoolSocialMedia_type')->nullable()->constrained('stp_core_metas')->onDelete('set null');



            $table->string('schoolSocialMedia_link');
            // $table->unsignedBigInteger('school_id')->nullable();
            $table->foreignId('school_id')->nullable()->constrained('stp_schools')->onDelete('set null');



            $table->integer('schoolSocialMedia_status');
            $table->integer('updated_by');
            $table->integer('created_by');
            $table->timestamps();

            // $table->foreign('schoolSocialMedia_type')->references('id')->on('stp_core_metas')->onDelete('set null');
            // $table->foreign('school_id')->references('id')->on('stp_schools')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_school_social_media');
    }
};
