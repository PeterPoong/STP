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
        Schema::create('stp_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_name');
            $table->string('package_detail');
            $table->foreignId('package_type')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->decimal('package_price');
            $table->integer('package_status')->default(1);
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
        Schema::dropIfExists('stp_packages');
    }
};
