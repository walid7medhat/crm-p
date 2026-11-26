<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance can exempt the admin role and specific users,
 * or lock only the people a super admin selects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_maintenance_settings', function (Blueprint $table) {
            $table->boolean('except_admin_role')->default(false)->after('branch_user_ids');
            $table->json('except_user_ids')->nullable()->after('except_admin_role');
            $table->json('target_user_ids')->nullable()->after('except_user_ids');
        });
    }

    public function down(): void
    {
        Schema::table('platform_maintenance_settings', function (Blueprint $table) {
            $table->dropColumn(['except_admin_role', 'except_user_ids', 'target_user_ids']);
        });
    }
};
