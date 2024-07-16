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
        Schema::create('stp_schools', function (Blueprint $table) {
            $table->id();
            $table->string('school_name');
            $table->string('school_fullDesc');
            $table->string('school_shortDesc');
            $table->string('school_address');
            $table->decimal('school_lg');
            $table->decimal('school_lat');
            $table->string('school_contactCountryCode');
            $table->string('school_contactNo');
            $table->string('school_officalWebsite');
            $table->string('school_logo');
            $table->string('school_status');
            $table->string('updated_by');
            $table->string('created_by');
            $table->timestamps();
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_schools');
    }
};
