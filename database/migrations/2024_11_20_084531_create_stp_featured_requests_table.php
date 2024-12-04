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
        Schema::create('stp_featured_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_name')->nullable();
            $table->foreignId('school_id')->nullable()->constrained('stp_schools')->onDelete('set null');
            $table->foreignId('featured_type')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->foreignId('request_id')->nullable()->constrained('stp_featured_requests')->onDelete('set null');
            $table->integer('request_quantity')->nullable();
            $table->integer('featured_duration')->nullable();
            $table->string('request_transaction_prove')->nullable();
            $table->integer('request_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_featured_requests');
    }
};
