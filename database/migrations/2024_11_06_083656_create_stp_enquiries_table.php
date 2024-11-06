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
        Schema::create('stp_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_name')->nullable();
            $table->string('enquiry_email')->nullable();
            $table->string('enquiry_phone')->nullable();
            $table->foreignId('enquiry_subject')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->longText('enquiry_message')->nullable();
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('enquiry_status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_enquiries');
    }
};
