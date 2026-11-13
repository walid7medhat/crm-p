<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance can target the whole CRM (all) or selected org-tree branches.
 * branch_user_ids stores CRM branch/office user ids (same tree as Account Emails).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_maintenance_settings', function (Blueprint $table) {
            $table->string('scope', 32)->default('all')->after('is_enabled');
            $table->json('branch_user_ids')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('platform_maintenance_settings', function (Blueprint $table) {
            $table->dropColumn(['scope', 'branch_user_ids']);
        });
    }
};
