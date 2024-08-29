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
        Schema::create('stp_states', function (Blueprint $table) {
            $table->id();
            $table->string('state_name');
            $table->string('state_isoCode');
            $table->string('country_code');
            $table->decimal('state_lg')->nullable();
            $table->decimal('state_lat')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('stp_countries')->onDelete('set null');
            $table->integer('state_status')->default(1);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_states');
    }
};
