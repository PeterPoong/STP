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
        Schema::create('stp_advertisement_banners', function (Blueprint $table) {
            $table->id();
            $table->string('banner_name');
            $table->string('banner_file');
            $table->string('banner_url');
            $table->foreignId('featured_id')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->dateTime('banner_start')->nullable();
            $table->dateTime('banner_end')->nullable();
            $table->integer('banner_status')->default(1);
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
        Schema::dropIfExists('stp_advertisement_banners');
    }
};
