<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('emergency_contact_relation')->nullable()->after('emergency_phone');
            $table->date('emirates_id_expiry_date')->nullable()->after('emirates_id_number');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_relation', 'emirates_id_expiry_date']);
        });
    }
};
