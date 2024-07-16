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
        Schema::create('stp_uni_staffs', function (Blueprint $table) {
            $table->id('id');
            $table->string('uniStaff_userName');
            $table->string('uniStaff_password');
            $table->integer('uniStaff_icNumber')->unique();
            $table->string('uniStaff_email')->unique();
            $table->string('uniStaff_countryCode');
            $table->string('uniStaff_contactNo');
            $table->foreignId('user_role')->nullable()->constrained('stp_core_metas')->onDelete('set null');
            $table->string('uniStaff_proilePic');
            $table->integer('uniStaff_status')->default(1);
            $table->integer('updated_by');
            $table->integer('created_by');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // $table->foreign('user_role')->references('id')->on('stp_core_metas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stp_uni_staffs');
    }
};
