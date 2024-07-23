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
            $table->string('school_email');
            $table->string('school_countryCode');
            $table->string('school_contactNo');
            $table->string('school_password');
            $table->string('school_fullDesc')->nullable();
            $table->string('school_shortDesc')->nullable();
            $table->string('school_address')->nullable();
            $table->decimal('school_lg')->nullable();
            $table->decimal('school_lat')->nullable();
            $table->string('school_officalWebsite')->nullable();
            $table->string('school_logo')->nullable();
            $table->string('school_status')->default(1);
            $table->string('updated_by')->nullable();
            $table->string('created_by')->nullable();
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
