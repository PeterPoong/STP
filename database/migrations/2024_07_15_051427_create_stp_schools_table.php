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
            $table->foreignId('country_id')->nullable()->constrained('stp_countries')->onDelete('set null');
            $table->foreignId('state_id')->nullable()->constrained('stp_states')->onDelete('set null');
            $table->foreignId('city_id')->nullable()->constrained('stp_cities')->onDelete('set null');
            $table->foreignId('institue_category')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->decimal('school_lg')->nullable();
            $table->decimal('school_lat')->nullable();
            $table->string('person_inChargeName')->nullable();
            $table->string('person_inChargeNumber')->nullable();
            $table->string('person_inChargeEmail')->nullable();
            $table->foreignId('account_type')->nullable()->constrained('stp_core_metas')->onDelete('set null');
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
