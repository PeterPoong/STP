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
        Schema::create('stp_user_details', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('user_id')->nullable()->constrained('stp_users')->onDelete('set null');
            $table->foreignId('gender')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->string('user_detailFirstName')->nullable();
            $table->string('user_detailLastName')->nullable();
            $table->string('user_detailAddress')->nullable();
            $table->string('user_detailCountry')->nullable();
            $table->string('user_detailCity')->nullable();
            $table->string('user_detailState')->default(1);
            $table->string('user_detailPostcode')->nullable();
            $table->string('user_detailStatus')->default(1);
            $table->string('updated_by')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            // $table->foreign('user_id')->references('id')->on('stp_users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_user_details');
    }
};
