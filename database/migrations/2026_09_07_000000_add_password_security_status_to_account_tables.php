<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['stp_users', 'stp_students', 'stp_schools'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('password_security_status', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['stp_users', 'stp_students', 'stp_schools'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('password_security_status');
            });
        }
    }
};
