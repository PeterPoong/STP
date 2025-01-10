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
        Schema::table('stp_courses_categories', function (Blueprint $table) {
            $table->foreignId('riasecTypes')->nullable()->constrained('stp_riasecTypes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stp_courses_categories', function (Blueprint $table) {
            $table->dropColumn('riasecTypes');
        });
    }
};
