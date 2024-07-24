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
        Schema::create('stp_cities', function (Blueprint $table) {
            $table->id();
            $table->string('city_name');
            $table->decimal('city_lat')->nullable();
            $table->decimal('city_lg')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('stp_states')->onDelete('set null');
            $table->integer('city_status')->default(1);
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
        Schema::dropIfExists('stp_cities');
    }
};
