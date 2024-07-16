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
        Schema::create('stp_user_media', function (Blueprint $table) {
            $table->id();
            $table->string('userMedia_name');
            $table->foreignId('user_id')->nullable()->constrained('stp_users')->onDelete('set null');
            $table->string('userMedia_location');
            $table->foreignId('userMedia_type')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->string('userMedia_format');
            $table->integer('userMedia_status');
            $table->integer('updated_by');
            $table->integer('created_by');
            $table->timestamps();

            // $table->foreign('user_id')->references('id')->on('Users')->onDelete('set null');
            // $table->foreign('userMedia_type')->references('id')->on('stp_core_metas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_user_media');
    }
};
