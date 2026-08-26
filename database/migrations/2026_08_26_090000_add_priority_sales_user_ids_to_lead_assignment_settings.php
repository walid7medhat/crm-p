<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_assignment_settings', function (Blueprint $table) {
            $table->json('priority_sales_user_ids')->nullable()->after('fallback_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignment_settings', function (Blueprint $table) {
            $table->dropColumn('priority_sales_user_ids');
        });
    }
};
